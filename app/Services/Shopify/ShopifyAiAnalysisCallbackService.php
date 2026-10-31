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
        if($existing)return ['request_reference'=>'CB-'.$existing['id'],'status'=>(string)$existing['status'],'priority'=>(string)$existing['priority'],'crm_lead_id'=>(int)($existing['crm_lead_id']??0),'message'=>'Callback request already received.'];
        $leadModel=$type==='hair'?new ShopifyHairAnalysisLeadModel():new ShopifySkinAnalysisLeadModel();
        $lead=$leadModel->where(['lead_reference'=>$reference,'store_domain'=>$store,'shopify_customer_id'=>$customer])->first();
        if(!$lead) throw new RuntimeException('AI_ANALYSIS_NOT_FOUND');
        $now=date('Y-m-d H:i:s'); $note=trim((string)($input['note']??''))?:null; $preferred=trim((string)($input['preferred_time']??''))?:null;
        $db=db_connect(); $db->transBegin();
        try {
            $crmId=$this->createCrmLead($lead,$type,$customer,$preferred,$note,$now);
            $id=$model->insert(['analysis_type'=>$type,'lead_id'=>(int)$lead['id'],'crm_lead_id'=>$crmId,'lead_reference'=>$reference,'analysis_id'=>$analysisId,'store_domain'=>$store,'shopify_customer_id'=>$customer,'preferred_time'=>$preferred,'note'=>$note,'status'=>'pending','priority'=>'high','created_at'=>$now,'updated_at'=>$now],true);
            if(!$id||$db->transStatus()===false) throw new RuntimeException('AI_ANALYSIS_CALLBACK_CREATE_FAILED');
            $db->transCommit();
        } catch(\Throwable $e) {
            $db->transRollback(); $race=$model->where(['store_domain'=>$store,'shopify_customer_id'=>$customer,'analysis_type'=>$type,'lead_reference'=>$reference])->first();
            if($race)return ['request_reference'=>'CB-'.$race['id'],'status'=>(string)$race['status'],'priority'=>(string)$race['priority'],'crm_lead_id'=>(int)($race['crm_lead_id']??0),'message'=>'Callback request already received.'];
            throw new RuntimeException('AI_ANALYSIS_CALLBACK_CREATE_FAILED',0,$e);
        }
        if(!$id)throw new RuntimeException('AI_ANALYSIS_CALLBACK_CREATE_FAILED');
        service('audit')->record('shopify.ai_analysis_callback_requested',['request_id'=>(int)$id,'analysis_type'=>$type,'lead_reference'=>$reference,'priority'=>'high']);
        return ['request_reference'=>'CB-'.$id,'status'=>'pending','priority'=>'high','crm_lead_id'=>$crmId,'message'=>'Callback request received.'];
    }
    public function adminList():array{return (new ShopifyAiAnalysisCallbackRequestModel())->select('shopify_ai_analysis_callback_requests.*, crm_leads.lead_number')->join('crm_leads','crm_leads.id=shopify_ai_analysis_callback_requests.crm_lead_id','left')->orderBy("FIELD(shopify_ai_analysis_callback_requests.priority, 'urgent', 'high', 'normal', 'low')",'',false)->orderBy('shopify_ai_analysis_callback_requests.created_at','ASC')->findAll(200);}

    private function createCrmLead(array $lead,string $type,string $customer,?string $preferred,?string $note,string $now):int
    {
        $db=db_connect(); $status=$db->table('crm_lead_statuses')->where('code','new')->get()->getRowArray(); $source=$db->table('crm_lead_sources')->where('code','other')->get()->getRowArray(); if(!$status||!$source)throw new RuntimeException('CRM_LEAD_MASTER_DATA_MISSING');
        $first=trim((string)($lead['first_name']??'')); $last=trim((string)($lead['last_name']??'')); $name=trim($first.' '.$last)?:'Shopify customer '.$customer; $mobile=trim((string)($lead['mobile_number']??'')); $normalized=$mobile!==''?preg_replace('/\D+/','',$mobile):null; $remarks='AI '.ucfirst($type).' Analysis callback requested. Shopify customer: '.$customer.'.'; if($preferred)$remarks.=' Preferred time: '.$preferred.'.'; if($note)$remarks.=' Note: '.$note;
        $id=$db->table('crm_leads')->insert(['lead_number'=>'TEMP-'.bin2hex(random_bytes(5)),'clinic_id'=>null,'full_name'=>$name,'mobile_number'=>$mobile?:null,'mobile_normalized'=>$normalized?:null,'telephone_country_code_id'=>null,'country_code'=>trim((string)($lead['country_code']??''))?:null,'email'=>trim((string)($lead['email']??''))?:null,'gender'=>trim((string)($lead['gender']??''))?:null,'date_of_birth'=>$lead['date_of_birth']??null,'source_id'=>(int)$source['id'],'interested_service'=>'AI '.ucfirst($type).' Analysis','lead_status_id'=>(int)$status['id'],'priority'=>'high','remarks'=>$remarks,'created_by'=>null,'created_at'=>$now,'updated_at'=>$now],true); if(!$id)throw new RuntimeException('CRM_LEAD_CREATE_FAILED'); $db->table('crm_leads')->where('id',(int)$id)->update(['lead_number'=>'LEAD-'.str_pad((string)$id,6,'0','0')]); return (int)$id;
    }
}
