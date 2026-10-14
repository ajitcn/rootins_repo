<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
final class SeedCrmFollowUpStatuses extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('follow_up_statuses')) return;
        foreach ([['scheduled','Scheduled'],['rescheduled','Rescheduled'],['missed','Missed']] as [$code,$name]) {
            if (! $this->db->table('follow_up_statuses')->where('normalized_code', $code)->countAllResults()) $this->db->table('follow_up_statuses')->insert(['code'=>$code,'name'=>$name,'normalized_code'=>$code,'status'=>1,'scope_key'=>'global','sort_order'=>0,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
        }
    }
    public function down() { }
}
