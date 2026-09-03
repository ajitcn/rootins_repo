<?php

namespace Admin\Models\Configuration;

use Admin\Models\AdminSettingRecordModel;
abstract class FixedSettingModel extends AdminSettingRecordModel
{
}
final class GeneralSettingsModel extends FixedSettingModel
{
    protected string $settingGroup = 'general-settings';
}
final class ClinicSettingsModel extends FixedSettingModel
{
    protected string $settingGroup = 'clinic-settings';
}
final class EmailSettingsModel extends FixedSettingModel
{
    protected string $settingGroup = 'email-settings';
}
final class SmsSettingsModel extends FixedSettingModel
{
    protected string $settingGroup = 'sms-settings';
}
final class WhatsAppSettingsModel extends FixedSettingModel
{
    protected string $settingGroup = 'whatsapp-settings';
}
final class NotificationSettingsModel extends FixedSettingModel
{
    protected string $settingGroup = 'notification-settings';
}
final class InvoiceTemplatesModel extends FixedSettingModel
{
    protected string $settingGroup = 'invoice-templates';
}
final class PrescriptionTemplatesModel extends FixedSettingModel
{
    protected string $settingGroup = 'prescription-templates';
}
