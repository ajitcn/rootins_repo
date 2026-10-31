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
        if($existing)return ['request_reference'=>'CB-'.$existing['id'],'status'=>(string)$existing['status'],'priority'=>(string)$existing['priority'],'message'=>'Callback request already received.'];
        $leadModel=$type==='hair'?new ShopifyHairAnalysisLeadModel():new ShopifySkinAnalysisLeadModel();
        $lead=$leadModel->where(['lead_reference'=>$reference,'store_domain'=>$store,'shopify_customer_id'=>$customer])->first();
        if(!$lead) throw new RuntimeException('AI_ANALYSIS_NOT_FOUND');
        $now=date('Y-m-d H:i:s');
        try{$id=$model->insert(['analysis_type'=>$type,'lead_id'=>(int)$lead['id'],'lead_reference'=>$reference,'analysis_id'=>$analysisId,'store_domain'=>$store,'shopify_customer_id'=>$customer,'preferred_time'=>trim((string)($input['preferred_time']??''))?:null,'note'=>trim((string)($input['note']??''))?:null,'status'=>'pending','priority'=>'high','created_at'=>$now,'updated_at'=>$now],true);}catch(\Throwable $e){$race=$model->where(['store_domain'=>$store,'shopify_customer_id'=>$customer,'analysis_type'=>$type,'lead_reference'=>$reference])->first();if($race)return ['request_reference'=>'CB-'.$race['id'],'status'=>(string)$race['status'],'priority'=>(string)$race['priority'],'message'=>'Callback request already received.'];throw new RuntimeException('AI_ANALYSIS_CALLBACK_CREATE_FAILED',0,$e);}
        if(!$id)throw new RuntimeException('AI_ANALYSIS_CALLBACK_CREATE_FAILED');
        service('audit')->record('shopify.ai_analysis_callback_requested',['request_id'=>(int)$id,'analysis_type'=>$type,'lead_reference'=>$reference,'priority'=>'high']);
        return ['request_reference'=>'CB-'.$id,'status'=>'pending','priority'=>'high','message'=>'Callback request received.'];
    }
    public function adminList():array{return (new ShopifyAiAnalysisCallbackRequestModel())->orderBy("FIELD(priority, 'urgent', 'high', 'normal', 'low')",'',false)->orderBy('created_at','ASC')->findAll(200);}
}
