<?php

namespace Admin\Models\Configuration;

use Admin\Models\AdminMasterRecordModel;
abstract class FixedMasterModel extends AdminMasterRecordModel
{
}
final class DoctorSpecializationModel extends FixedMasterModel
{
    protected string $masterType = 'doctor-specializations';
}
final class DepartmentModel extends FixedMasterModel
{
    protected string $masterType = 'departments';
}
final class ServiceModel extends FixedMasterModel
{
    protected string $masterType = 'services';
}
final class ConsultationFeeTemplateModel extends FixedMasterModel
{
    protected string $masterType = 'consultation-fees';
}
final class CommissionTemplateModel extends FixedMasterModel
{
    protected string $masterType = 'commission-templates';
}
final class PaymentModeModel extends FixedMasterModel
{
    protected string $masterType = 'payment-modes';
}
final class TaxRateModel extends FixedMasterModel
{
    protected string $masterType = 'tax-rates';
}
