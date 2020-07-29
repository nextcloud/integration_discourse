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

require_once __DIR__ . '/../../vendor/autoload.php';
use phpseclib\Crypt\RSA;

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
        $clientID = $this->config->getUserValue($this->userId, 'discourse', 'client_id', '');
        $pubKey = $this->config->getAppValue('discourse', 'public_key', '');
        $privKey = $this->config->getAppValue('discourse', 'private_key', '');

        if ($clientID === '') {
            // random string of 32 chars length
            $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyz';
            $clientID = substr(str_shuffle($permitted_chars), 0, 32);
            $this->config->setUserValue($this->userId, 'discourse', 'client_id', $clientID);
        }
        if ($pubKey === '' or $privKey === '') {
            $rsa = new RSA();
            $rsa->setPrivateKeyFormat(RSA::PRIVATE_FORMAT_PKCS1);
            $rsa->setPublicKeyFormat(RSA::PUBLIC_FORMAT_PKCS1);
            $keys = $rsa->createKey(2048);
            $pubKey = $keys['publickey'];
            $privKey = $keys['privatekey'];

            $this->config->setAppValue('discourse', 'public_key', $pubKey);
            $this->config->setAppValue('discourse', 'private_key', $privKey);
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
