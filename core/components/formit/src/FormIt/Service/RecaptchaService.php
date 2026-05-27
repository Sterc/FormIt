<?php
namespace Sterc\FormIt\Service;

use MODX\Revolution\modX;

class RecaptchaService
{
    const VERIFY_URL     = 'https://www.google.com/recaptcha/api/siteverify';
    const OPT_SITE_KEY   = 'siteKey';
    const OPT_SECRET_KEY = 'secretKey';
    const OPT_MIN_SCORE  = 'minScore';

    /** @var modX $modx */
    public $modx;
    /** @var \Sterc\FormIt $formit */
    public $formit;
    /** @var array $config */
    public $config = [];

    public function __construct($formit, array $config = [])
    {
        $this->formit = $formit;
        $this->modx   = $formit->modx;
        $this->modx->lexicon->load('formit:recaptcha');
        $this->config = array_merge([
            self::OPT_SITE_KEY   => $this->modx->getOption('formit.recaptcha_site_key',   $config, ''),
            self::OPT_SECRET_KEY => $this->modx->getOption('formit.recaptcha_secret_key', $config, ''),
            self::OPT_MIN_SCORE  => (float) $this->modx->getOption('formit.recaptcha_min_score', $config, 0.5),
        ], $config);
    }

    /**
     * Renders two hidden fields required for reCAPTCHA v3.
     * The Google API JS must be registered separately via Request.php.
     *
     * @param array $scriptProperties
     * @return string HTML with two hidden inputs
     */
    public function render(array $scriptProperties = []): string
    {
        if (empty($this->config[self::OPT_SITE_KEY])) {
            return $this->error($this->modx->lexicon('recaptcha.no_api_key'));
        }

        $action = htmlspecialchars(
            (string) $this->modx->getOption('recaptchaAction', $scriptProperties, ''),
            ENT_QUOTES
        );

        $html = '<input type="hidden" name="g-recaptcha-response" value="">'
              . '<input type="hidden" name="g-recaptcha-action" value="' . $action . '">';

        $this->modx->setPlaceholder('formit.recaptcha_html', $html);
        if (!empty($scriptProperties['placeholderPrefix'])) {
            $this->modx->setPlaceholder($scriptProperties['placeholderPrefix'] . 'recaptcha_html', $html);
        }

        return $html;
    }

    /**
     * Verifies a reCAPTCHA v3 token via Google Siteverify API.
     *
     * @param string $token The token from g-recaptcha-response POST field
     * @return bool True if verification passed and score >= minScore
     */
    public function verify(string $token): bool
    {
        if (empty($this->config[self::OPT_SECRET_KEY])) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[FormIt] reCAPTCHA: secret key not configured');
            return false;
        }

        if (empty($token)) {
            return false;
        }

        $payload = http_build_query([
            'secret'   => $this->config[self::OPT_SECRET_KEY],
            'response' => $token,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);

        $ch = curl_init(self::VERIFY_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        unset($ch);

        if ($raw === false || $err) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[FormIt] reCAPTCHA curl error: ' . $err);
            return false;
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['success'])) {
            return false;
        }

        $score    = isset($data['score']) ? (float) $data['score'] : 0.0;
        $minScore = (float) $this->config[self::OPT_MIN_SCORE];

        return $score >= $minScore;
    }

    /**
     * Returns an error string.
     *
     * @param string $message
     * @return string
     */
    protected function error(string $message = ''): string
    {
        return $message;
    }
}
