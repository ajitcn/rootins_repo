<?php
namespace Admin\Controllers;
use App\Controllers\BaseAdminController;
use Admin\Services\CrmLeadService;
use Admin\Services\CrmFollowUpWorkflowService;
use Admin\Models\Configuration\ServiceModel;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;
class Crm extends BaseAdminController
{
    private CrmLeadService $crm;
    private CrmFollowUpWorkflowService $followUpWorkflow;
    public function initController(\CodeIgniter\HTTP\RequestInterface $request, ResponseInterface $response, \Psr\Log\LoggerInterface $logger) { parent::initController($request,$response,$logger); $this->crm=$this->createCrmService(); $this->followUpWorkflow=new CrmFollowUpWorkflowService(); }
    protected function createCrmService(): CrmLeadService { return new CrmLeadService(); }
    public function leads() { $filters=$this->filters(['search','status_id','source_id','priority','assigned_user_id','from','to']); $data=$this->crm->listLeads($filters, max(1,(int)$this->request->getGet('page')),20); return view(CRM_VIEW.'pages/leads',array_merge($data,$this->crm->masters(),['filters'=>$filters,'pageTitle'=>'Lead Management'])); }
    public function followUps() { $filters=$this->filters(['search','follow_up_status','follow_up_type_id','assigned_user_id','from','to','lead_id']); $data=$this->crm->listFollowUps($filters,max(1,(int)$this->request->getGet('page')),20); return view(CRM_VIEW.'pages/followups',array_merge($data,$this->crm->masters(),['filters'=>$filters,'pageTitle'=>'Lead Follow-ups'])); }
    public function history(int $id) { try { $filters=$this->filters(['event_type','actor','from','to']); $data=$this->crm->historyPage($id,max(1,(int)$this->request->getGet('page')),20,$filters); $data['eventTypes']=$this->crm->historyEventTypes($id); $data['summary']=$this->crm->historySummary($id); $data['ai_analyses']=(new \App\Services\Crm\CrmAiAnalysisReferenceService())->forLead($id); $data['follow_ups']=$this->crm->listFollowUps(['lead_id'=>$id],1,100)['rows']; return view(CRM_VIEW.'pages/history',$data); } catch (RuntimeException $e) { return $this->response->setStatusCode(404)->setBody('Lead not found.'); } }
    public function convertPatient(int $id) { if(!$this->guard('crm.lead.convert'))return $this->denied(); return $this->write(fn()=>['data'=>$this->crm->convertToPatient($id,(int)($this->user['id']??0))]); }
    public function convertAppointment(int $id) { if(!$this->guard('crm.lead.convert'))return $this->denied(); return $this->write(function() use ($id) { $input=$this->request->getPost(); $lead=$this->crm->findLead($id); if(($input['service_name']??'')===''&&$lead)$input['service_name']=$lead['service_name']??$lead['interested_service']??''; return ['data'=>$this->crm->convertToAppointment($id,$input,(int)($this->user['id']??0))]; }); }
    public function saveLead(?int $id=null) { if(!$this->guard($id?'crm.lead.edit':'crm.lead.create'))return $this->denied(); if(!$this->validate(['gender'=>['label'=>'Gender','rules'=>'permit_empty|in_list[Male,Female,Other]|max_length[20]'],'age'=>['label'=>'Age','rules'=>'permit_empty|is_natural_no_zero|less_than_equal_to[120]']]))return $this->json(['status'=>false,'msg'=>'Please correct the highlighted fields.','errors'=>$this->validator->getErrors()],422); return $this->write(function() use ($id) { $input=$this->request->getPost(); $serviceValue=trim((string)($input['interested_service']??'')); if($serviceValue!==''){ if(!ctype_digit($serviceValue)||($this->crm->clinicId()>0&&(new ServiceModel())->forScope($this->crm->clinicId())->where('id',(int)$serviceValue)->where('status',1)->first()===null)||($this->crm->clinicId()<=0&&(new ServiceModel())->where('id',(int)$serviceValue)->where('status',1)->first()===null)) throw new RuntimeException(json_encode(['interested_service'=>'Select an active service.'])); $input['interested_service']=(string)(int)$serviceValue; } else { $input['interested_service']=null; } $result=$this->crm->saveLead($input,$id,(int)($this->user['id']??0)); $db=db_connect(); if($db->fieldExists('age','crm_leads')){$age=trim((string)($input['age']??'')); $db->table('crm_leads')->where('id',(int)($result['id']??$id))->update(['age'=>$age===''?null:(int)$age]); $result=$this->crm->findLead((int)($result['id']??$id))??$result;} return ['data'=>$result]; }); }
    public function saveFollowUp(?int $id=null) { if(!$this->guard($id?'crm.followup.edit':'crm.followup.create'))return $this->denied(); return $this->write(fn()=>['data'=>$this->crm->saveFollowUp($this->request->getPost(),$id,(int)($this->user['id']??0))]); }
    public function followUpDetails(int $id)
    {
        if (! $this->guard('crm.followup.view')) {
            return $this->denied();
        }

        try {
            $data = $this->followUpWorkflow->details($id);

            if ($this->request->isAJAX()) {
                return $this->json(['status' => true, 'data' => $data]);
            }

            // The CRM controller owns the route, but the shared Admin layout
            // owns this detail template. Keep the browser page and AJAX
            // endpoint on the same data path.
            return view(ADMIN_VIEW . 'pages/crm/followup_detail', $data);
        } catch (RuntimeException $e) {
            $message = $e->getMessage();

            if ($this->request->isAJAX()) {
                return $this->json(['status' => false, 'msg' => $message], $message === 'Follow-up not found.' ? 404 : 500);
            }

            if ($message === 'Follow-up not found.') {
                return $this->response->setStatusCode(404)->setBody('Follow-up not found.');
            }

            log_message('error', 'CRM follow-up detail page failed ' . json_encode([
                'follow_up_id' => $id,
                'exception_class' => get_class($e),
                'message' => $message,
            ], JSON_UNESCAPED_SLASHES));

            return $this->response->setStatusCode(500)->setBody('The follow-up details could not be loaded.');
        } catch (\Throwable $e) {
            log_message('error', 'CRM follow-up detail page failed ' . json_encode([
                'follow_up_id' => $id,
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_SLASHES));

            if ($this->request->isAJAX()) {
                return $this->json(['status' => false, 'msg' => 'The follow-up details could not be loaded.'], 500);
            }

            return $this->response->setStatusCode(500)->setBody('The follow-up details could not be loaded.');
        }
    }
    public function followUpHistory(int $id) { if(!$this->guard('crm.history.view'))return $this->denied(); try{return $this->json(['status'=>true,'data'=>$this->followUpWorkflow->details($id)]);}catch(RuntimeException $e){return $this->json(['status'=>false,'msg'=>'Follow-up history not found.'],404);} }
    public function addFollowUpComment(int $id) { if(!$this->guard('crm.followup.edit'))return $this->denied(); return $this->write(fn()=>['data'=>$this->followUpWorkflow->addComment($id,$this->request->getPost(),(int)($this->user['id']??0))]); }
    public function rescheduleFollowUp(int $id) { if(!$this->guard('crm.followup.edit'))return $this->denied(); return $this->write(fn()=>['data'=>$this->followUpWorkflow->reschedule($id,$this->request->getPost(),(int)($this->user['id']??0))]); }
    public function updateFollowUpStatus(int $id) { if(!$this->guard('crm.followup.edit'))return $this->denied(); return $this->write(fn()=>['data'=>$this->followUpWorkflow->updateStatus($id,(string)$this->request->getPost('follow_up_status'),(string)$this->request->getPost('comment'),(int)($this->user['id']??0))]); }
    public function followUpAction(int $id,string $action) { $permission=in_array($action,['complete'],true)?'crm.followup.complete':'crm.followup.cancel'; if(!$this->guard($permission))return $this->denied(); if(!in_array($action,['complete','reschedule','cancel','missed'],true))return $this->json(['status'=>false,'msg'=>'Invalid follow-up action.'],422); return $this->write(fn()=>['data'=>$this->followUpWorkflow->changeStatus($id,['complete'=>'completed','reschedule'=>'rescheduled','cancel'=>'cancelled','missed'=>'missed'][$action],$this->request->getPost(),(int)($this->user['id']??0))]); }
    public function deleteLead(int $id) { $lead=$this->crm->findLead($id);if(!$lead)return $this->json(['status'=>false,'msg'=>'Lead not found.'],404);(new \Admin\Models\CrmLeadModel())->delete($id);$this->audit->record('crm.lead_deleted',['lead_id'=>$id,'clinic_id'=>$lead['clinic_id']]);return $this->json(['status'=>true,'msg'=>'Lead deleted.']); }
    private function filters(array $keys): array { $out=[];foreach($keys as $key)$out[$key]=trim((string)($this->request->getGet($key)??''));return $out; }
    private function guard(string $permission): bool { return $this->hasPermission($permission); }
    private function denied() { return $this->json(['status'=>false,'msg'=>'You are not authorized to perform this action.'],403); }
    private function write(callable $callback) { try{return $this->json(array_merge(['status'=>true,'msg'=>'Saved successfully.'],$callback()));}catch(RuntimeException $e){$errors=json_decode($e->getMessage(),true);return $this->json(['status'=>false,'msg'=>is_array($errors)?'Please correct the highlighted fields.':$e->getMessage(),'errors'=>is_array($errors)?$errors:[]],422);}catch(\Throwable $e){log_message('error','CRM request failed: '.$e->getMessage());return $this->json(['status'=>false,'msg'=>'The CRM request could not be completed.'],500);} }
    private function json(array $payload,int $status=200){return $this->response->setStatusCode($status)->setJSON($payload);}
}
