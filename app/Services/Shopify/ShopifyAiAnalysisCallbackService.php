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
        if($existing)return ['request_reference'=>'CB-'.$existing['id'],'status'=>(string)$existing['status'],'priority'=>(string)$existing['priority'],'crm_lead_id'=>(int)($existing['crm_lead_id']??0),'follow_up_id'=>(int)($existing['follow_up_id']??0),'message'=>'Callback request already received.'];
        $leadModel=$type==='hair'?new ShopifyHairAnalysisLeadModel():new ShopifySkinAnalysisLeadModel();
        $lead=$leadModel->where(['lead_reference'=>$reference,'store_domain'=>$store,'shopify_customer_id'=>$customer])->first();
        if(!$lead) throw new RuntimeException('AI_ANALYSIS_NOT_FOUND');
        $now=date('Y-m-d H:i:s'); $note=trim((string)($input['note']??''))?:null; $preferred=trim((string)($input['preferred_time']??''))?:null;
        $db=db_connect(); $db->transBegin();
        try {
            $crmService = new ShopifyAiAnalysisCrmService();
            $crmId = $crmService->ensureLead($type, $lead);
            $leadModel->update((int) $lead['id'], ['crm_lead_id' => $crmId]);
            if (! $db->fieldExists('crm_lead_id', 'shopify_ai_analysis_callback_requests') || ! $db->fieldExists('follow_up_id', 'shopify_ai_analysis_callback_requests')) throw new RuntimeException('AI_ANALYSIS_CRM_LINKAGE_MIGRATION_REQUIRED');
            $id=$model->insert(['analysis_type'=>$type,'lead_id'=>(int)$lead['id'],'crm_lead_id'=>$crmId,'lead_reference'=>$reference,'analysis_id'=>$analysisId,'store_domain'=>$store,'shopify_customer_id'=>$customer,'preferred_time'=>$preferred,'note'=>$note,'status'=>'pending','priority'=>'high','created_at'=>$now,'updated_at'=>$now],true);
            $followUpId=(new ShopifyAiAnalysisCrmService())->createCallbackFollowUp($crmId,$type,$preferred,$note);
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
    public function adminList():array{return (new ShopifyAiAnalysisCallbackRequestModel())->select('shopify_ai_analysis_callback_requests.*, crm_leads.lead_number')->join('crm_leads','crm_leads.id=shopify_ai_analysis_callback_requests.crm_lead_id','left')->orderBy("FIELD(shopify_ai_analysis_callback_requests.priority, 'urgent', 'high', 'normal', 'low')",'',false)->orderBy('shopify_ai_analysis_callback_requests.created_at','ASC')->findAll(200);}

}
