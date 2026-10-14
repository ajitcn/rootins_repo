<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
final class AddDeliveryProviderPermissions extends Migration
{
    public function up(): void
    {
        foreach ([['Delivery Provider','View Delivery Providers','delivery_provider.view'],['Delivery Provider','Manage Delivery Providers','delivery_provider.manage']] as [$module,$name,$key]) {
            $row=$this->db->table('permissions')->where('permission_key',$key)->get(1)->getRowArray();
            if(!$row){$this->db->table('permissions')->insert(['module'=>$module,'permission_name'=>$name,'permission_key'=>$key,'status'=>1,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);$row=['id'=>$this->db->insertID()];}
            $role=$this->db->table('roles')->where('role_code','ADMIN')->where('status',1)->get(1)->getRowArray();
            if($role&&!$this->db->table('role_permissions')->where('role_id',$role['id'])->where('permission_id',$row['id'])->get(1)->getRowArray())$this->db->table('role_permissions')->insert(['role_id'=>$role['id'],'permission_id'=>$row['id'],'created_at'=>date('Y-m-d H:i:s')]);
        }
    }
    public function down(): void {}
}
