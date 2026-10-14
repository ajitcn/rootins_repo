<?php

namespace Pharmacy\Services;

use DateTimeImmutable;
use DateTimeZone;

/** Clinic-scoped operational analytics; all lists are bounded and server-side. */
final class PharmacyDashboardService
{
    private const ORDERS = ['pending', 'processing', 'fulfilled', 'cancelled', 'returned'];
    private const PAYMENTS = ['pending', 'authorized', 'paid', 'failed', 'refunded'];
    private const DELIVERIES = ['pending', 'packed', 'dispatched', 'out_for_delivery', 'delivered', 'failed', 'returned'];
    private const REVIEWS = ['draft', 'submitted', 'approved', 'rejected', 'clarification_requested', 'cancelled'];

    public function __construct(private readonly PharmacyScope $scope) {}

    /** @return array<string,mixed> */
    public function summary(array $input = []): array
    {
        $range = $this->range($input);
        $filters = $this->filters($input);
        $filters['date_error'] = $range['date_error'];
        $data = $this->empty($range, $filters);
        $clinic = $this->scope->clinicId();
        if ($clinic === null) return $data;
        $ordersAllowed = $this->scope->can('pharmacy.order.view');
        $reviewsAllowed = $this->scope->can('pharmacy.prescription.review.view') || $this->scope->can('pharmacy.prescription.view');
        if ($ordersAllowed) {
            $data['orders'] = $this->orderAnalytics($clinic, $range, $filters);
            $data['latest']['orders'] = $this->latestOrders($clinic, $range, $filters);
            $data['kpis']['orders_today'] = $this->orderCount($clinic, $this->today(), $this->today(), $filters);
            $data['kpis']['orders_month'] = $this->orderCount($clinic, $this->today()->modify('first day of this month'), $this->today(), $filters);
            $data['kpis']['open_orders'] = $data['orders']['statuses']['pending'] + $data['orders']['statuses']['processing'];
            $data['kpis']['completed_orders'] = $data['orders']['statuses']['fulfilled'];
            $data['kpis']['cancelled_orders'] = $data['orders']['statuses']['cancelled'];
        }
        if ($reviewsAllowed) {
            $data['prescriptions'] = $this->reviewAnalytics($clinic, $range, $filters);
            $data['latest']['prescriptions'] = $this->latestPrescriptions($clinic, $range, $filters);
            $data['kpis']['pending_reviews'] = $data['prescriptions']['statuses']['submitted'] + $data['prescriptions']['statuses']['clarification_requested'];
            $data['kpis']['approved_awaiting'] = $this->approvedAwaiting($clinic, $range);
        }
        if ($this->scope->can('pharmacy.billing.view')) {
            $data['payments'] = $this->paymentAnalytics($clinic, $range, $filters);
            $data['latest']['payments'] = $this->latestPayments($clinic, $range, $filters);
            $data['kpis']['pending_payments'] = $data['payments']['statuses']['pending'] + $data['payments']['statuses']['authorized'];
            $data['kpis']['paid_amount'] = $data['payments']['paid_amount'];
            $data['kpis']['refunded_amount'] = $data['payments']['refunded_amount'];
        }
        if ($this->scope->can('pharmacy.delivery.view')) {
            $data['delivery'] = $this->deliveryAnalytics($clinic, $range, $filters);
            $data['latest']['deliveries'] = $this->latestDeliveries($clinic, $range, $filters);
            $data['kpis']['active_deliveries'] = array_sum(array_intersect_key($data['delivery']['statuses'], array_flip(['pending','packed','dispatched','out_for_delivery'])));
            $data['kpis']['delivered_orders'] = $data['delivery']['statuses']['delivered'];
            $data['kpis']['failed_returned'] = $data['delivery']['statuses']['failed'] + $data['delivery']['statuses']['returned'];
        }
        if ($this->scope->can('pharmacy.inventory.view')) {
            $data['stock'] = $this->stockAnalytics();
            $data['latest']['stock_alerts'] = $this->stockAlerts();
            $data['kpis']['available_stock'] = $data['stock']['available_batches'];
            $data['kpis']['low_stock'] = $data['stock']['low_stock_medicines'];
            $data['kpis']['near_expiry'] = $data['stock']['near_expiry_batches'];
        }
        $data['last_updated'] = date('d-m-Y H:i');
        return $data;
    }

    private function empty(array $range, array $filters): array
    {
        return ['range'=>$range,'filters'=>$filters,'last_updated'=>'','kpis'=>array_fill_keys(['orders_today','orders_month','open_orders','completed_orders','cancelled_orders','pending_reviews','approved_awaiting','pending_payments','paid_amount','refunded_amount','active_deliveries','delivered_orders','failed_returned','available_stock','low_stock','near_expiry'],0),'orders'=>['statuses'=>array_fill_keys(self::ORDERS,0),'daily'=>[],'completion_rate'=>0,'cancellation_rate'=>0],'prescriptions'=>['statuses'=>array_fill_keys(self::REVIEWS,0),'average_review_hours'=>null],'payments'=>['statuses'=>array_fill_keys(self::PAYMENTS,0),'paid_amount'=>0.0,'pending_amount'=>0.0,'refunded_amount'=>0.0,'manual_reconciliations'=>0,'providers'=>[]],'delivery'=>['statuses'=>array_fill_keys(self::DELIVERIES,0),'completion_rate'=>0,'average_fulfilment_hours'=>null],'stock'=>['active_medicines'=>0,'available_batches'=>0,'low_stock_medicines'=>0,'near_expiry_batches'=>0,'expired_inactive'=>0,'out_of_stock_medicines'=>0,'top_medicines'=>[]],'latest'=>['orders'=>[],'prescriptions'=>[],'payments'=>[],'deliveries'=>[],'stock_alerts'=>[]]];
    }

    private function filters(array $input): array
    {
        $pick = static fn(string $value, array $list): string => in_array($value, $list, true) ? $value : '';
        return ['order_status'=>$pick(trim((string)($input['order_status']??'')),self::ORDERS),'payment_status'=>$pick(trim((string)($input['payment_status']??'')),self::PAYMENTS),'delivery_status'=>$pick(trim((string)($input['delivery_status']??'')),self::DELIVERIES),'prescription_status'=>$pick(trim((string)($input['prescription_status']??'')),self::REVIEWS)];
    }

    private function range(array $input): array
    {
        $today = $this->today();
        $from = $this->parse((string)($input['from'] ?? '')) ?? $today->modify('first day of this month');
        $to = $this->parse((string)($input['to'] ?? '')) ?? $today;
        $dateError = $to < $from;
        if ($dateError) [$from, $to] = [$to, $from];
        return ['from'=>$from->format('Y-m-d'),'to'=>$to->format('Y-m-d'),'from_display'=>$from->format('d-m-Y'),'to_display'=>$to->format('d-m-Y'),'date_error'=>$dateError];
    }

    private function ordersQuery(int $clinic, array $range, array $filters)
    {
        $q=db_connect()->table('medicine_orders o')->select('o.id,o.order_number,o.status,o.payment_status,o.delivery_status,o.created_at,o.updated_at,o.payment_provider,o.payment_reference,p.registration_number,p.first_name,p.middle_name,p.last_name,i.invoice_number,i.total')->join('patients p','p.id=o.patient_id')->join('invoices i','i.order_id=o.id AND i.clinic_id=o.clinic_id','left')->where('o.clinic_id',$clinic)->where('p.clinic_id',$clinic)->where('o.created_at >=',$range['from'].' 00:00:00')->where('o.created_at <=',$range['to'].' 23:59:59'); $this->applyOrderFilters($q,$filters); return $q;
    }
    private function applyOrderFilters($q,array $f): void { if($f['order_status']!=='')$q->where('o.status',$f['order_status']); if($f['payment_status']!=='')$q->where('o.payment_status',$f['payment_status']); if($f['delivery_status']!=='')$q->where('o.delivery_status',$f['delivery_status']); }
    private function orderAnalytics(int $clinic,array $range,array $filters): array { $s=array_fill_keys(self::ORDERS,0); foreach($this->ordersQuery($clinic,$range,$filters)->select('o.status,COUNT(*) total')->groupBy('o.status')->get()->getResultArray() as $r){if(isset($s[$r['status']]))$s[$r['status']]=(int)$r['total'];} $total=array_sum($s); return ['statuses'=>$s,'daily'=>$this->dailyOrders($clinic,$range,$filters),'completion_rate'=>$total?round($s['fulfilled']*100/$total,1):0,'cancellation_rate'=>$total?round($s['cancelled']*100/$total,1):0]; }
    private function dailyOrders(int $clinic,array $range,array $filters): array { $q=db_connect()->table('medicine_orders o')->select('DATE(o.created_at) day,COUNT(*) total')->where('o.clinic_id',$clinic)->where('o.created_at >=',$range['from'].' 00:00:00')->where('o.created_at <=',$range['to'].' 23:59:59'); $this->applyOrderFilters($q,$filters); return $q->groupBy('DATE(o.created_at)')->orderBy('day','ASC')->get()->getResultArray(); }
    private function orderCount(int $clinic,DateTimeImmutable $from,DateTimeImmutable $to,array $filters): int { $q=db_connect()->table('medicine_orders o')->where('o.clinic_id',$clinic)->where('o.created_at >=',$from->format('Y-m-d').' 00:00:00')->where('o.created_at <=',$to->format('Y-m-d').' 23:59:59'); $this->applyOrderFilters($q,$filters); return (int)$q->countAllResults(); }
    private function reviewAnalytics(int $clinic,array $range,array $filters): array { $s=array_fill_keys(self::REVIEWS,0); $q=db_connect()->table('prescriptions')->select('status,COUNT(*) total')->where('clinic_id',$clinic)->where('prescription_date >=',$range['from'])->where('prescription_date <=',$range['to']); if($filters['prescription_status']!=='')$q->where('status',$filters['prescription_status']); foreach($q->groupBy('status')->get()->getResultArray() as $r){if(isset($s[$r['status']]))$s[$r['status']]=(int)$r['total'];} return ['statuses'=>$s,'average_review_hours'=>null]; }
    private function paymentAnalytics(int $clinic,array $range,array $filters): array { $s=array_fill_keys(self::PAYMENTS,0);$paid=$pending=$refunded=0.0;$q=db_connect()->table('medicine_orders o')->select('o.payment_status,COUNT(*) total,SUM(CASE WHEN o.payment_status="paid" THEN COALESCE(i.total,0) ELSE 0 END) paid_amount,SUM(CASE WHEN o.payment_status IN ("pending","authorized") THEN COALESCE(i.total,0) ELSE 0 END) pending_amount,SUM(CASE WHEN o.payment_status="refunded" THEN COALESCE(i.total,0) ELSE 0 END) refunded_amount')->join('invoices i','i.order_id=o.id AND i.clinic_id=o.clinic_id','left')->where('o.clinic_id',$clinic)->where('o.created_at >=',$range['from'].' 00:00:00')->where('o.created_at <=',$range['to'].' 23:59:59');$this->applyOrderFilters($q,$filters);foreach($q->groupBy('o.payment_status')->get()->getResultArray() as $r){if(isset($s[$r['payment_status']]))$s[$r['payment_status']]=(int)$r['total'];$paid+=(float)$r['paid_amount'];$pending+=(float)$r['pending_amount'];$refunded+=(float)$r['refunded_amount'];}$manual=db_connect()->table('audit_logs')->where('clinic_id',$clinic)->where('action','pharmacy.payment_reconciled')->where('occurred_at >=',$range['from'].' 00:00:00')->where('occurred_at <=',$range['to'].' 23:59:59')->like('context','manual')->countAllResults();return ['statuses'=>$s,'paid_amount'=>$paid,'pending_amount'=>$pending,'refunded_amount'=>$refunded,'manual_reconciliations'=>$manual,'providers'=>$this->paymentProviders($clinic,$range,$filters)]; }
    private function paymentProviders(int $clinic,array $range,array $filters): array { $q=db_connect()->table('medicine_orders o')->select('COALESCE(pp.provider_name,o.payment_provider,"Not recorded") provider,COUNT(*) total,SUM(CASE WHEN o.payment_status="paid" THEN COALESCE(i.total,0) ELSE 0 END) paid_amount')->join('invoices i','i.order_id=o.id AND i.clinic_id=o.clinic_id','left')->join('payment_providers pp','pp.provider_code=o.payment_provider','left')->where('o.clinic_id',$clinic)->where('o.created_at >=',$range['from'].' 00:00:00')->where('o.created_at <=',$range['to'].' 23:59:59');$this->applyOrderFilters($q,$filters);return $q->groupBy('o.payment_provider')->orderBy('total','DESC')->get()->getResultArray(); }
    private function deliveryAnalytics(int $clinic,array $range,array $filters): array { $s=array_fill_keys(self::DELIVERIES,0);$q=db_connect()->table('medicine_orders o')->select('o.delivery_status,COUNT(*) total')->where('o.clinic_id',$clinic)->where('o.created_at >=',$range['from'].' 00:00:00')->where('o.created_at <=',$range['to'].' 23:59:59');$this->applyOrderFilters($q,$filters);foreach($q->groupBy('o.delivery_status')->get()->getResultArray() as $r){if(isset($s[$r['delivery_status']]))$s[$r['delivery_status']]=(int)$r['total'];}$total=array_sum($s);return ['statuses'=>$s,'completion_rate'=>$total?round($s['delivered']*100/$total,1):0,'average_fulfilment_hours'=>null]; }
    private function stockAnalytics(): array { $today=$this->today()->format('Y-m-d');$near=$this->today()->modify('+30 days')->format('Y-m-d');$db=db_connect();$all=$db->table('medicine_inventory_batches b')->select('b.medicine_id,b.quantity_available,b.expiry_date,b.status,m.medicine_name')->join('medicines m','m.id=b.medicine_id')->get()->getResultArray();$low=$nearCount=$expired=$out=$available=0;$totals=[];foreach($all as $r){$qty=(int)$r['quantity_available'];$active=$r['status']==='active';$expiredRow=$r['expiry_date']<$today;if($active&&!$expiredRow&&$qty>0)$available++;if($active&&!$expiredRow&&$qty>0&&$qty<=10)$low++;if(!$active||$expiredRow)$expired++;if($active&&!$expiredRow&&$qty<=0)$out++;if($active&&!$expiredRow&&$r['expiry_date']<=$near)$nearCount++;$id=(int)$r['medicine_id'];if(!isset($totals[$id]))$totals[$id]=['medicine_name'=>$r['medicine_name'],'quantity'=>0];$totals[$id]['quantity']+=$qty;}uasort($totals,static fn($a,$b)=>$b['quantity']<=>$a['quantity']);return ['active_medicines'=>(int)$db->table('medicines')->where('status','active')->countAllResults(),'available_batches'=>$available,'low_stock_medicines'=>$low,'near_expiry_batches'=>$nearCount,'expired_inactive'=>$expired,'out_of_stock_medicines'=>$out,'top_medicines'=>array_slice(array_values($totals),0,5)]; }
    private function approvedAwaiting(int $clinic,array $range): int { return (int)db_connect()->table('prescriptions p')->join('medicine_orders o','o.prescription_id=p.id','left')->where('p.clinic_id',$clinic)->where('p.status','approved')->where('p.prescription_date >=',$range['from'])->where('p.prescription_date <=',$range['to'])->where('o.id IS NULL',null,false)->countAllResults(); }
    private function latestOrders(int $clinic,array $range,array $filters): array { return $this->normalizeRows($this->ordersQuery($clinic,$range,$filters)->orderBy('o.created_at','DESC')->get(8)->getResultArray()); }
    private function latestPrescriptions(int $clinic,array $range,array $filters): array { $q=db_connect()->table('prescriptions p')->select('p.prescription_number,p.prescription_date,p.status,p.updated_at,pa.registration_number,pa.first_name,pa.last_name,d.first_name doctor_first_name,d.last_name doctor_last_name,(SELECT COUNT(*) FROM prescription_items pi WHERE pi.prescription_id=p.id) medicine_count')->join('patients pa','pa.id=p.patient_id')->join('doctors d','d.id=p.doctor_id')->where('p.clinic_id',$clinic)->where('p.prescription_date >=',$range['from'])->where('p.prescription_date <=',$range['to']);if($filters['prescription_status']!=='')$q->where('p.status',$filters['prescription_status']);return $this->normalizeRows($q->orderBy('p.updated_at','DESC')->get(8)->getResultArray()); }
    private function latestPayments(int $clinic,array $range,array $filters): array { $q=db_connect()->table('medicine_orders o')->select('o.order_number,o.payment_status,o.payment_provider,o.payment_reference,o.last_reconciled_at,i.invoice_number,i.total,pp.provider_name,pa.registration_number,pa.first_name,pa.last_name')->join('patients pa','pa.id=o.patient_id')->join('invoices i','i.order_id=o.id AND i.clinic_id=o.clinic_id','left')->join('payment_providers pp','pp.provider_code=o.payment_provider','left')->where('o.clinic_id',$clinic)->where('o.created_at >=',$range['from'].' 00:00:00')->where('o.created_at <=',$range['to'].' 23:59:59');$this->applyOrderFilters($q,$filters);return $this->normalizeRows($q->orderBy('o.last_reconciled_at','DESC')->get(8)->getResultArray()); }
    private function latestDeliveries(int $clinic,array $range,array $filters): array { $q=db_connect()->table('medicine_orders o')->select('o.order_number,o.delivery_status,o.delivery_provider,o.delivery_provider_name_snapshot,o.tracking_reference,o.dispatched_at,o.delivered_at,pa.registration_number,pa.first_name,pa.last_name')->join('patients pa','pa.id=o.patient_id')->where('o.clinic_id',$clinic)->where('o.created_at >=',$range['from'].' 00:00:00')->where('o.created_at <=',$range['to'].' 23:59:59');$this->applyOrderFilters($q,$filters);return $this->normalizeRows($q->orderBy('o.updated_at','DESC')->get(8)->getResultArray()); }
    private function stockAlerts(): array { $today=$this->today()->format('Y-m-d');$near=$this->today()->modify('+30 days')->format('Y-m-d');return db_connect()->table('medicine_inventory_batches b')->select('b.batch_number,b.quantity_available,b.expiry_date,b.status,m.medicine_name')->join('medicines m','m.id=b.medicine_id')->groupStart()->where('b.status !=','active')->orWhere('b.expiry_date <',$today)->orWhere('b.quantity_available <=',10)->orWhere('b.expiry_date <=',$near)->groupEnd()->orderBy('b.expiry_date','ASC')->get(8)->getResultArray(); }
    private function normalizeRows(array $rows): array { return array_map(function(array $r):array{$r['patient_name']=trim(implode(' ',array_filter([$r['first_name']??'',$r['middle_name']??'',$r['last_name']??'',$r['patient_first_name']??'',$r['patient_last_name']??''])))?:'Patient not recorded';$r['patient_number']=(string)($r['registration_number']??'Not recorded');$r['doctor_name']=trim(implode(' ',array_filter([$r['doctor_first_name']??'',$r['doctor_last_name']??''])))?:'Not recorded';$r['provider_name']=$r['provider_name']??($r['payment_provider']??'Not recorded');$r['delivery_provider_name_snapshot']=$r['delivery_provider_name_snapshot']??($r['delivery_provider']??'Not recorded');foreach(['created_at','updated_at','prescription_date','last_reconciled_at','dispatched_at','delivered_at'] as $f)if(isset($r[$f]))$r[$f.'_display']=$this->displayDate((string)$r[$f]);return $r;},$rows); }
    private function today(): DateTimeImmutable { return new DateTimeImmutable('today',new DateTimeZone((string)(config('App')->appTimezone?:date_default_timezone_get()))); }
    private function parse(string $v): ?DateTimeImmutable { $d=DateTimeImmutable::createFromFormat('!d-m-Y',$v);return $d!==false&&$d->format('d-m-Y')===$v?$d:null; }
    private function displayDate(string $v): string { $d=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$v)?:DateTimeImmutable::createFromFormat('!Y-m-d',substr($v,0,10));return $d!==false?$d->format('d-m-Y'):'Not recorded'; }
}
