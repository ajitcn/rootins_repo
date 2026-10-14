<?php
namespace Admin\Controllers;
use Admin\Models\DeliveryProviderModel;
use App\Controllers\BaseAdminController;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

final class DeliveryProvider extends BaseAdminController
{
    public function index(): string
    {
        $model = new DeliveryProviderModel(); $search = trim((string) $this->request->getGet('search'));
        if ($search !== '') $model->groupStart()->like('provider_name', $search)->orLike('provider_code', $search)->groupEnd();
        return view(ADMIN_VIEW . 'pages/delivery_providers/index', ['rows' => $model->orderBy('sort_order')->orderBy('provider_name')->paginate(15, 'delivery_providers'), 'pager' => $model->pager, 'search' => $search]);
    }
    public function save(): ResponseInterface
    {
        if (!$this->request->isAJAX()) return $this->response->setStatusCode(405)->setJSON(['status'=>false,'msg'=>'Invalid request.']);
        $id=(int)$this->request->getPost('id'); $name=trim((string)$this->request->getPost('provider_name')); $code=strtolower(trim((string)$this->request->getPost('provider_code'))); $template=trim((string)$this->request->getPost('tracking_url_template')); $errors=[];
        if ($name===''||mb_strlen($name)>120) $errors['provider_name']='Provider name is required.';
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{1,59}$/',$code)) $errors['provider_code']='Use 2-60 lowercase letters, numbers, hyphens, or underscores.';
        if ($template!=='' && (filter_var($template, FILTER_VALIDATE_URL) === false || preg_match('#^https?://#i', $template) !== 1)) $errors['tracking_url_template']='Use a valid http:// or https:// URL. A tracking placeholder is optional.';
        $model=new DeliveryProviderModel(); if($model->where('provider_name',$name)->where('id !=',$id)->first())$errors['provider_name']='Provider name already exists.'; if($model->where('provider_code',$code)->where('id !=',$id)->first())$errors['provider_code']='Provider code already exists.'; if($errors)return $this->response->setStatusCode(422)->setJSON(['status'=>false,'msg'=>reset($errors),'errors'=>$errors]);
        $data=['provider_name'=>$name,'provider_code'=>$code,'tracking_url_template'=>$template?:null,'support_contact'=>trim((string)$this->request->getPost('support_contact'))?:null,'status'=>$this->request->getPost('status')==='0'?0:1,'sort_order'=>max(0,(int)$this->request->getPost('sort_order'))];
        try { if($id){if(!$model->find($id))return $this->response->setStatusCode(404)->setJSON(['status'=>false,'msg'=>'Provider not found.']);$model->update($id,$data);$event='updated';}else{$id=(int)$model->insert($data,true);$event='created';} $this->audit->record('admin.delivery_provider_'.$event,['id'=>$id,'provider_code'=>$code]); return $this->response->setJSON(['status'=>true,'msg'=>'Delivery provider '.$event.'.']); } catch(Throwable $e){log_message('error','Delivery provider save failed: '.$e->getMessage());return $this->response->setStatusCode(500)->setJSON(['status'=>false,'msg'=>'Provider could not be saved safely.']);}
    }
    public function toggle(int $id): ResponseInterface { $model=new DeliveryProviderModel();$row=$model->find($id);if(!$row)return $this->response->setStatusCode(404)->setJSON(['status'=>false,'msg'=>'Provider not found.']);$status=(int)$row['status']===1?0:1;$model->update($id,['status'=>$status,'archived_at'=>$status?null:date('Y-m-d H:i:s')]);$this->audit->record('admin.delivery_provider_status_changed',['id'=>$id,'status'=>$status]);return $this->response->setJSON(['status'=>true,'msg'=>'Provider status updated.']); }
    public function delete(int $id): ResponseInterface { $model=new DeliveryProviderModel();$row=$model->find($id);if(!$row)return $this->response->setStatusCode(404)->setJSON(['status'=>false,'msg'=>'Provider not found.']);$used=db_connect()->table('medicine_orders')->where('delivery_provider_id',$id)->countAllResults()>0;if($used)$model->update($id,['status'=>0,'archived_at'=>date('Y-m-d H:i:s')]);else$model->delete($id);$this->audit->record('admin.delivery_provider_deleted_or_archived',['id'=>$id,'archived'=>$used]);return $this->response->setJSON(['status'=>true,'msg'=>$used?'Provider archived because orders reference it.':'Provider deleted.']); }
}
