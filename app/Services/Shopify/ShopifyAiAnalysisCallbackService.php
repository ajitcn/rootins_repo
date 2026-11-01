<?php
namespace App\Services\Shopify;

use App\Modules\Shopify\Models\ShopifyAiAnalysisCallbackRequestModel;
use App\Modules\Shopify\Models\ShopifyHairAnalysisLeadModel;
use App\Modules\Shopify\Models\ShopifySkinAnalysisLeadModel;
use InvalidArgumentException;
use RuntimeException;

final class ShopifyAiAnalysisCallbackService
{
    public function submit(array $context, array $input): array
    {
        $type=strtolower(trim((string)($input['type']??''))); $reference=trim((string)($input['reference']??'')); $analysisId=trim((string)($input['analysis_id']??''));
        $store=trim((string)($context['store_domain']??'')); $customer=trim((string)($context['customer_id']??''));
        if(!in_array($type,['hair','skin'],true)||$reference===''||$analysisId===''||$reference!==$analysisId) throw new InvalidArgumentException('A valid analysis type and matching reference are required.');
        if($store===''||$customer==='') throw new RuntimeException('SHOPIFY_PROXY_CONTEXT_MISSING');
        $model=new ShopifyAiAnalysisCallbackRequestModel();
        $existing=$model->where(['store_domain'=>$store,'shopify_customer_id'=>$customer,'analysis_type'=>$type,'lead_reference'=>$reference])->first();
        if($existing){
            $existing=$this->repairExistingCallback($existing,$type,$store,$customer,$reference);
            return ['request_reference'=>'CB-'.$existing['id'],'status'=>(string)$existing['status'],'priority'=>(string)$existing['priority'],'crm_lead_id'=>(int)($existing['crm_lead_id']??0),'follow_up_id'=>(int)($existing['follow_up_id']??0),'message'=>'Callback request already received.'];
        }
        $leadModel=$type==='hair'?new ShopifyHairAnalysisLeadModel():new ShopifySkinAnalysisLeadModel();
        $lead=$this->findAnalysis($type,$reference,$store,$customer);
        if(!$lead) throw new RuntimeException('AI_ANALYSIS_NOT_FOUND');
        $now=date('Y-m-d H:i:s'); $note=trim((string)($input['note']??''))?:null; $preferredDate=trim((string)($input['preferred_date']??''))?:null; $preferredSlot=trim((string)($input['preferred_slot']??''))?:null; $preferredSlotLabel=trim((string)($input['preferred_slot_label']??''))?:null; $preferred=trim((string)($input['preferred_time']??''))?:null;
        if($preferredDate!==null&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$preferredDate))throw new InvalidArgumentException('preferred_date must use YYYY-MM-DD format.');
        if($preferredDate!==null&&!$preferred) $preferred=$preferredDate.($preferredSlot!==null?' '.$preferredSlot:'');
        $db=db_connect(); $db->transBegin();
        try {
            $crmService = new ShopifyAiAnalysisCrmService();
            $crmId = $crmService->ensureLead($type, $lead);
            if (! empty($lead['id'])) $leadModel->update((int) $lead['id'], ['crm_lead_id' => $crmId]);
            $schema = [
                'callback_crm_lead_id' => $db->fieldExists('crm_lead_id', 'shopify_ai_analysis_callback_requests'),
                'callback_follow_up_id' => $db->fieldExists('follow_up_id', 'shopify_ai_analysis_callback_requests'),
                'callback_preferred_date' => $db->fieldExists('preferred_date', 'shopify_ai_analysis_callback_requests'),
                'callback_preferred_slot' => $db->fieldExists('preferred_slot', 'shopify_ai_analysis_callback_requests'),
                'callback_preferred_slot_label' => $db->fieldExists('preferred_slot_label', 'shopify_ai_analysis_callback_requests'),
                'follow_up_priority' => $db->fieldExists('priority', 'crm_lead_followups'),
            ];
            if (in_array(false, $schema, true)) {
                log_message('error', 'Shopify AI callback schema incomplete '.json_encode([
                    'category' => 'AI_ANALYSIS_CRM_LINKAGE_MIGRATION_REQUIRED',
                    'schema' => $schema,
                ], JSON_UNESCAPED_SLASHES));
                throw new RuntimeException('AI_ANALYSIS_CRM_LINKAGE_MIGRATION_REQUIRED');
            }
            $id=$model->insert(['analysis_type'=>$type,'lead_id'=>(int)$lead['id'],'crm_lead_id'=>$crmId,'lead_reference'=>$reference,'analysis_id'=>$analysisId,'store_domain'=>$store,'shopify_customer_id'=>$customer,'preferred_date'=>$preferredDate,'preferred_slot'=>$preferredSlot,'preferred_slot_label'=>$preferredSlotLabel,'preferred_time'=>$preferred,'note'=>$note,'status'=>'pending','priority'=>'high','created_at'=>$now,'updated_at'=>$now],true);
            $followUpId=(new ShopifyAiAnalysisCrmService())->createCallbackFollowUp($crmId,$type,$preferred,$note,$preferredDate,$preferredSlot,$preferredSlotLabel);
            $model->update((int)$id,['follow_up_id'=>$followUpId,'updated_at'=>$now]);
            if(!$id||$db->transStatus()===false) throw new RuntimeException('AI_ANALYSIS_CALLBACK_CREATE_FAILED');
            $db->transCommit();
        } catch(\Throwable $e) {
            $db->transRollback(); $race=$model->where(['store_domain'=>$store,'shopify_customer_id'=>$customer,'analysis_type'=>$type,'lead_reference'=>$reference])->first();
            if($race)return ['request_reference'=>'CB-'.$race['id'],'status'=>(string)$race['status'],'priority'=>(string)$race['priority'],'crm_lead_id'=>(int)($race['crm_lead_id']??0),'follow_up_id'=>(int)($race['follow_up_id']??0),'message'=>'Callback request already received.'];
            log_message('error','Shopify AI callback transaction failed '.json_encode(['analysis_type'=>$type,'lead_reference'=>$reference,'category'=>$e->getMessage(),'exception_class'=>get_class($e)],JSON_UNESCAPED_SLASHES));
            if($e instanceof RuntimeException && $e->getMessage()==='AI_ANALYSIS_CRM_LINKAGE_MIGRATION_REQUIRED') throw $e;
            throw new RuntimeException('AI_ANALYSIS_CALLBACK_CREATE_FAILED',0,$e);
        }
        if(!$id)throw new RuntimeException('AI_ANALYSIS_CALLBACK_CREATE_FAILED');
        service('audit')->record('shopify.ai_analysis_callback_requested',['request_id'=>(int)$id,'analysis_type'=>$type,'lead_reference'=>$reference,'priority'=>'high']);
        return ['request_reference'=>'CB-'.$id,'status'=>'pending','priority'=>'high','crm_lead_id'=>$crmId,'follow_up_id'=>$followUpId,'message'=>'Callback request received.'];
    }
    private function repairExistingCallback(array $existing,string $type,string $store,string $customer,string $reference):array
    {
        $db=db_connect();
        $followUpId=(int)($existing['follow_up_id']??0);
        if($followUpId>0 && $db->table('crm_lead_followups')->where('id',$followUpId)->where('deleted_at',null)->countAllResults()>0)return $existing;
        $leadModel=$type==='hair'?new ShopifyHairAnalysisLeadModel():new ShopifySkinAnalysisLeadModel();
        $lead=$this->findAnalysis($type,$reference,$store,$customer);
        if(!$lead)throw new RuntimeException('AI_ANALYSIS_NOT_FOUND');
        $crmId=(int)($existing['crm_lead_id']??0);
        if($crmId<=0)$crmId=(new ShopifyAiAnalysisCrmService())->ensureLead($type,$lead);
        $db->transBegin();
        try{
            $followUpId=(new ShopifyAiAnalysisCrmService())->createCallbackFollowUp($crmId,$type,$existing['preferred_time']??null,$existing['note']??null,$existing['preferred_date']??null,$existing['preferred_slot']??null,$existing['preferred_slot_label']??null);
            (new ShopifyAiAnalysisCallbackRequestModel())->update((int)$existing['id'],['crm_lead_id'=>$crmId,'follow_up_id'=>$followUpId,'updated_at'=>date('Y-m-d H:i:s')]);
            if($db->transStatus()===false)throw new RuntimeException('AI_ANALYSIS_CALLBACK_REPAIR_FAILED');
            $db->transCommit();
            log_message('info','Shopify AI callback follow-up repaired '.json_encode(['request_id'=>(int)$existing['id'],'analysis_type'=>$type,'lead_reference'=>$reference,'crm_lead_id'=>$crmId,'follow_up_id'=>$followUpId],JSON_UNESCAPED_SLASHES));
            $existing['crm_lead_id']=$crmId;$existing['follow_up_id']=$followUpId;
            return $existing;
        }catch(\Throwable $e){
            $db->transRollback();
            log_message('error','Shopify AI callback follow-up repair failed '.json_encode(['request_id'=>(int)$existing['id'],'analysis_type'=>$type,'lead_reference'=>$reference,'category'=>$e->getMessage(),'exception_class'=>get_class($e)],JSON_UNESCAPED_SLASHES));
            throw $e;
        }
    }
    private function findAnalysis(string $type,string $reference,string $store,string $customer):?array
    {
        $db=db_connect();
        if($db->tableExists('shopify_ai_analysis_records')){
            $common=$db->table('shopify_ai_analysis_records')->where(['analysis_type'=>$type,'lead_reference'=>$reference,'store_domain'=>$store,'shopify_customer_id'=>$customer])->get()->getRowArray();
            if($common && (int)($common['legacy_record_id']??0)>0){
                $legacyId=(int)($common['legacy_record_id']??0);
                $common['id']=$legacyId;
                return $common;
            }
        }
        $model=$type==='hair'?new ShopifyHairAnalysisLeadModel():new ShopifySkinAnalysisLeadModel();
        return $model->where(['lead_reference'=>$reference,'store_domain'=>$store,'shopify_customer_id'=>$customer])->first()?:null;
    }
    public function adminList():array{return (new ShopifyAiAnalysisCallbackRequestModel())->select('shopify_ai_analysis_callback_requests.*, crm_leads.lead_number')->join('crm_leads','crm_leads.id=shopify_ai_analysis_callback_requests.crm_lead_id','left')->orderBy("FIELD(shopify_ai_analysis_callback_requests.priority, 'urgent', 'high', 'normal', 'low')",'',false)->orderBy('shopify_ai_analysis_callback_requests.created_at','ASC')->findAll(200);}

}
