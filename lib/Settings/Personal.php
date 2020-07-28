<?php
namespace OCA\Discourse\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IL10N;
use OCP\IConfig;
use OCP\Settings\ISettings;
use OCP\Util;
use OCP\IURLGenerator;
use OCP\IInitialStateService;

class Personal implements ISettings {

    private $request;
    private $config;
    private $dataDirPath;
    private $urlGenerator;
    private $l;

    public function __construct(
                        string $appName,
                        IL10N $l,
                        IRequest $request,
                        IConfig $config,
                        IURLGenerator $urlGenerator,
                        IInitialStateService $initialStateService,
                        $userId) {
        $this->appName = $appName;
        $this->urlGenerator = $urlGenerator;
        $this->request = $request;
        $this->l = $l;
        $this->config = $config;
        $this->initialStateService = $initialStateService;
        $this->userId = $userId;
    }

    /**
     * @return TemplateResponse
     */
    public function getForm() {
        $token = $this->config->getUserValue($this->userId, 'discourse', 'token', '');
        $url = $this->config->getUserValue($this->userId, 'discourse', 'url', '');

        // for OAuth
        $clientID = $this->config->getAppValue('discourse', 'client_id', '');
        $pubKey = $this->config->getAppValue('discourse', 'public_key', '');
        if ($clientID === '') {
            // random string of 32 chars length
            $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $clientID = substr(str_shuffle($permitted_chars), 0, 32);
            $this->config->setAppValue('discourse', 'client_id', $clientID);
        }
        if ($pubKey === '') {
            $pubKey = md5(rand());
            $this->config->setAppValue('discourse', 'public_key', $pubKey);
        }

        $userConfig = [
            'token' => $token,
            'url' => $url,
            'client_id' => $clientID,
            'public_key' => $pubKey,
        ];
        $this->initialStateService->provideInitialState($this->appName, 'user-config', $userConfig);
        return new TemplateResponse('discourse', 'personalSettings');
    }

    public function getSection() {
        return 'linked-accounts';
    }

    public function getPriority() {
        return 10;
    }
}
