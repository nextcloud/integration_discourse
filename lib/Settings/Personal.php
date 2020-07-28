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
        $clientID = $this->config->getAppValue('discourse', 'client_id', '');
        $pubKey = $this->config->getAppValue('discourse', 'public_key', '');
        $privKey = $this->config->getAppValue('discourse', 'private_key', '');

        if ($clientID === '') {
            // random string of 32 chars length
            $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $clientID = substr(str_shuffle($permitted_chars), 0, 32);
            $this->config->setAppValue('discourse', 'client_id', $clientID);
        }
        if ($pubKey === '' or $privKey === '') {
            $rsa = new RSA();
            $keys = $rsa->createKey(2048);
            $pubKey = $keys['publickey'];
            $pubKeyParts = explode("\n", $pubKey);
            $pubKeyParts = array_splice($pubKeyParts, 1, count($pubKeyParts) - 2);
            $pubKey = implode('', $pubKeyParts);

            $privKey = $keys['privatekey'];
            $privKeyParts = explode("\n", $privKey);
            $privKeyParts = array_splice($privKeyParts, 1, count($privKeyParts) - 2);
            $privKey = implode('', $privKeyParts);

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
