<?php
namespace CRM\Services;

class CrmLeadService extends \Admin\Services\CrmLeadService
{
    public function saveFollowUp(array $input, ?int $id, int $actor): array
    {
        $time = trim((string) ($input['follow_up_time'] ?? ''));
        if (! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $time)) {
            throw new \RuntimeException(json_encode(['follow_up_time' => 'Enter a valid follow-up time.']));
        }

        return parent::saveFollowUp($input, $id, $actor);
    }
}
