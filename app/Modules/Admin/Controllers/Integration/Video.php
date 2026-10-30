<?php

namespace Admin\Controllers\Integration;

use App\Controllers\BaseAdminController;
use App\Services\Video\VideoConfigurationService;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

final class Video extends BaseAdminController
{
    public function index(): string
    {
        return view(ADMIN_VIEW . 'pages/integrations/video', ['video' => (new VideoConfigurationService())->safe(), 'canManage' => $this->hasPermission('settings.manage')]);
    }

    public function save(): ResponseInterface
    {
        if (! $this->request->isAJAX() || ! $this->hasPermission('settings.manage')) return $this->json(405, false, 'Invalid request.');
        try {
            $saved = (new VideoConfigurationService())->save($this->request->getPost(), (int) ($this->user['id'] ?? 0));
            $this->audit->record('admin.video_configuration_saved', ['provider' => 'twilio', 'enabled' => (int) ($saved['status'] ?? 0)]);

            return $this->response->setJSON(['status' => true, 'msg' => 'Twilio Video configuration saved.', 'data' => $saved]);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(422, false, $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Video provider configuration save failed: ' . $exception->getMessage());

            return $this->json(500, false, 'Video provider configuration could not be saved safely.');
        }
    }

    public function test(): ResponseInterface
    {
        if (! $this->request->isAJAX() || ! $this->hasPermission('settings.manage')) return $this->json(405, false, 'Invalid request.');
        $video = (new VideoConfigurationService())->safe();
        $ok = (bool) $video['configured'] && (int) $video['status'] === 1;
        $this->audit->record($ok ? 'admin.video_configuration_check_succeeded' : 'admin.video_configuration_check_failed', ['provider' => 'twilio', 'configured' => $ok]);

        return $this->response->setStatusCode($ok ? 200 : 422)->setJSON(['status' => $ok, 'msg' => $ok ? 'Twilio Video configuration is present.' : 'Twilio Video configuration is incomplete.', 'data' => ['provider' => 'twilio', 'configured' => (bool) $video['configured'], 'status' => (int) $video['status']]]);
    }

    private function json(int $status, bool $ok, string $message): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON(['status' => $ok, 'msg' => $message]);
    }
}
