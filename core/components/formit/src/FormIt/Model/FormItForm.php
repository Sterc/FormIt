<?php
namespace Sterc\FormIt\Model;

use xPDO\xPDO;
use MODX\Revolution\modX;
use MODX\Revolution\modSystemSetting;
use MODX\Revolution\Sources\modMediaSource;

/**
 * Class FormItForm
 *
 * @property string $form
 * @property string $context_key
 * @property string $values
 * @property string $ip
 * @property integer $date
 * @property boolean $encrypted
 * @property integer $encryption_type
 * @property string $hash
 *
 * @package Sterc\FormIt\Model
 */
class FormItForm extends \xPDO\Om\xPDOSimpleObject
{
    private $oldEncryptKey;
    private $encryptKey;
    private $ivKey;
    private $method = 'AES-256-CBC';
    public $path;
    public $encBlocks = 10000;

    function __construct(& $xpdo)
    {
        parent:: __construct($xpdo);

        $this->setSecretKeys();
    }

    public function encrypt($value)
    {
        if (!function_exists('openssl_encrypt')) {
            $this->xpdo->log(MODx::LOG_LEVEL_ERROR, '[FormIt] openssl_encrypt is not available. Please install OpenSSL. See http://www.php.net/manual/en/openssl.requirements.php for more information.');

            return false;
        }

        $value = base64_encode(openssl_encrypt($value, $this->method, $this->encryptKey, 0, $this->ivKey));

        return $value;
    }

    public function decrypt($value, $type = 2)
    {
        /* Check for encryption type; 1 = old mcrypt method */
        if ($type === 1) {
            if (!function_exists('mcrypt_decrypt')) {
                $this->xpdo->log(MODx::LOG_LEVEL_ERROR, '[FormIt] mcrypt_decrypt is not available. See http://php.net/manual/en/mcrypt.requirements.php for more information.');

                return false;
            }

            return rtrim(
                mcrypt_decrypt(
                    MCRYPT_RIJNDAEL_256,
                    md5($this->oldEncryptKey),
                    base64_decode($value),
                    MCRYPT_MODE_CBC,
                    md5(md5($this->oldEncryptKey))
                ),
                "\0"
            );
        }

        if (!function_exists('openssl_decrypt')) {
            $this->xpdo->log(MODx::LOG_LEVEL_ERROR, '[FormIt] openssl_decrypt is not available. Please install OpenSSL. See http://www.php.net/manual/en/openssl.requirements.php for more information.');

            return false;
        }

        /* Return default openssl decrypted values */

        return openssl_decrypt(base64_decode($value), $this->method, $this->encryptKey, 0, $this->ivKey);
    }

    public function generatePseudoRandomHash($bytes = 16)
    {
        $hash = bin2hex(openssl_random_pseudo_bytes($bytes, $strong));
        if (!$strong) {
            $hash = $this->generatePseudoRandomHash($bytes);
        }

        return $hash;
    }

    public function setSecretKeys()
    {
        $encryptkey = $this->xpdo->getOption('formit.form_encryptkey', null, null, false);
        if (!$encryptkey) {
            $encryptkey = $this->xpdo->site_id;
            $setting = $this->xpdo->getObject(
                modSystemSetting::class,
                ['key' => 'formit.form_encryptkey', 'namespace' => 'formit']
            );

            if (!$setting) {
                $setting = $this->xpdo->newObject(modSystemSetting::class);
                $setting->set('key', 'formit.form_encryptkey');
                $setting->set('namespace', 'formit');
            }
            $setting->set('value', $encryptkey);
            $setting->save();
        }

        $this->oldEncryptKey = $encryptkey;
        $this->encryptKey    = hash('sha256', $encryptkey);
        $this->ivKey         = substr(hash('sha256', md5($encryptkey)), 0, 16);
    }

    public function validateStoreAttachment($config)
    {
        $error          = '';
        $mediasourceId  = $this->xpdo->getOption('formit.attachment.mediasource');
        $mediasource    = $this->xpdo->getObject(modMediaSource::class, $mediasourceId);

        if (!$mediasource) {
            $error = $this->xpdo->lexicon('formit.storeAttachment_mediasource_error') . $mediasourceId;
        } else {
            $prop = $mediasource->get('properties');
            $path = $prop['basePath']['value'] . $this->xpdo->getOption('formit.attachment.path');
            if (!is_dir(MODX_BASE_PATH . $path)) {
                mkdir(MODX_BASE_PATH . $path);
            }

            if (!is_writable(MODX_BASE_PATH . $path)) {
                $error = $this->xpdo->lexicon('formit.storeAttachment_access_error') . ' ' . $path;
            } else {
                $this->xpdo->setPlaceholder($config['placeholderPrefix'] . 'storeAttachment_path', $path);
            }
        }

        if(!empty($error)){
            $this->xpdo->log(MODx::LOG_LEVEL_ERROR, '[FormIt] ' . $error);
        }

        $this->xpdo->setPlaceholder($config['placeholderPrefix'] . 'error.storeAttachment', $error);
        $this->path = $path;

        return $error;
    }

    public function storeAttachments($config)
    {
        if ($config['fieldNames']) {
            $formFieldNames = explode(',', $config['fieldNames']);
            foreach ($formFieldNames as $formFieldName) {
                $parts = explode('==', $formFieldName);
                $fieldLabels[trim($parts[0])] = trim($parts[1]);
            }
        }

        if ($this->xpdo->getPlaceholder($config['placeholderPrefix'] . 'error.storeAttachment') == '') {
            $path = $this->xpdo->getPlaceholder($config['placeholderPrefix'] . 'storeAttachment_path');
            $encrypted = $this->encrypted;
            if ($encrypted) {
                $old_data = $this->xpdo->fromJSON($this->decrypt($this->values));
            } else {
                $old_data = $this->xpdo->fromJSON($this->values);
            }

            $url = $this->xpdo->getOption('manager_url') . '?a=home&namespace=formit&formid=' . $this->id;

            foreach ($_FILES as $key => $value) {
                $data_key = [];
                if (is_array($value['name'])) {
                    foreach ($value['name'] as $fKey => $fValue) {
                        $enc_name = $this->encrypt($value['name'][$fKey]);
                        $resp = $this->saveFile(
                            $enc_name,
                            $value['name'][$fKey],
                            $value['tmp_name'][$fKey],
                            $value['error'][$fKey],
                            $path
                        );
                        if ($resp) {
                            $data_key[] = "<a target='_blank' href='" . $url . '&file=' . $enc_name . "'>" . $value['name'][$fKey] . '</a><br>';
                        }
                    }
                } else {
                    $enc_name = $this->encrypt($value['name']);
                    $resp = $this->saveFile(
                        $enc_name,
                        $value['name'],
                        $value['tmp_name'],
                        $value['error'],
                        $path
                    );
                    if ($resp) {
                        $data_key[] = "<a target='_blank' href='" . $url . '&file=' . $enc_name . "'>" . $value['name'] . '</a><br>';
                    }
                }

                if ($fieldLabels && $fieldLabels[$key]) {
                    $key = $fieldLabels[$key];
                }

                $old_data[$key] = implode('', $data_key);
                $new_data = $this->xpdo->toJSON($old_data);

                if ($encrypted) {
                    $new_data = $this->encrypt($new_data);
                }

                $this->set('values', $new_data);
                $this->save();
            }
        }
    }

    public function saveFile($enc_name, $name, $tmp_name, $error, $path)
    {
        $info = pathinfo($name);
        $ext  = strtolower(trim($info['extension'] ?? ''));
        if ($error !== 0) {
            $this->xpdo->log(MODx::LOG_LEVEL_ERROR, '[FormItSaveForm] ' . $this->xpdo->lexicon('formit.storeAttachment_file_upload_error'));

            return;
        }

        $allowedFileTypes = array_merge(
            explode(',', $this->xpdo->getOption('upload_images')),
            explode(',', $this->xpdo->getOption('upload_media')),
            explode(',', $this->xpdo->getOption('upload_flash')),
            explode(',', $this->xpdo->getOption('upload_files', null, ''))
        );

        $allowedFileTypes = array_filter(
            array_unique(
                array_map('strtolower', array_map('trim', $allowedFileTypes))
            )
        );
        /* Make sure that dangerous file types are not allowed */
        $defaultBlockedExtensions = 'php,php4,php5,php7,php8,phar,htm,html,phtml,shtml,js,mjs,bin,csh,out,run,sh,htaccess,svg,svgz';
        $blockedExtensions = $this->xpdo->getOption(
            'formit.upload_blocked_extensions',
            null,
            $defaultBlockedExtensions
        );
        $blockedExtensions = array_filter(
            array_unique(
                array_map('strtolower', array_map('trim', explode(',', (string)$blockedExtensions)))
            )
        );
        $allowedFileTypes = array_diff($allowedFileTypes, $blockedExtensions);

        /* Check file extension */
        if ($ext === '' || !in_array($ext, $allowedFileTypes, true)) {
            $this->xpdo->log(MODx::LOG_LEVEL_ERROR, '[FormItSaveForm] ' . $this->xpdo->lexicon('formit.storeAttachment_file_ext_error'));

            return;
        }

        /* Check filesize */
        $maxFileSize = $this->xpdo->getOption('upload_maxsize', null, 1048576);
        $size        = filesize($tmp_name);
        if ($size > $maxFileSize) {
            $this->xpdo->log(
                MODx::LOG_LEVEL_ERROR,
                '[FormItSaveForm] ' . $this->xpdo->lexicon('formit.storeAttachment_file_size_error')
            );

            return;
        }

        if (empty($path)) {
            $standardPath = $this->xpdo->getOption(
                'formit.assets_path',
                null,
                $this->xpdo->getOption('assets_path', null, MODX_CORE_PATH) . 'components/formit/attachments/'
            );

            if (!is_dir($standardPath)) {
                mkdir($standardPath);
            }
            $basePath = $standardPath . $this->id . '/';
        } else {
            $basePath = MODX_BASE_PATH . $path . $this->id . '/';
        }

        if (!is_dir($basePath)) {
            mkdir($basePath);
        }

        $target = $basePath . $enc_name;

        return $this->encryptFile($tmp_name, $target);
        /*$_FILES[$key]['tmp_name'] = $target;
        $_SESSION['formit']['tmp_files'][] = $target;*/
    }

    public function downloadFile($fileget)
    {
        $config['placeholderPrefix'] = 'pl.';
        $val = $this->validateStoreAttachment($config);

        if (!empty($val)) {
            return $val;
        }

        if ($this->path == '') {
            $file = $this->xpdo->getOption(
                'formit.assets_path',
                null,
                $this->xpdo->getOption('assets_path', null, MODX_CORE_PATH)
            ) . 'components/formit/attachments/' . $this->get('id') . '/' . $fileget;

        } else {
            $file = MODX_BASE_PATH . $this->path . $this->get('id') . '/' . $fileget;
        }

        $fd = fopen($file, 'r');
        if (!$fd) {
            return 'Cant read file! ' . $file;
        }

        return $this->decryptFile($file);
    }

    function encryptFile($source, $dest)
    {
        if (!function_exists('openssl_encrypt')) {
            $error = '[FormIt] openssl_encrypt is not available. Please install OpenSSL. See http://www.php.net/manual/en/openssl.requirements.php for more information.';
            $this->xpdo->log(MODx::LOG_LEVEL_ERROR, $error);

            return $error;
        }

        $key = $this->encryptKey;
        $iv  = $this->ivKey;
        if ($fpOut = fopen($dest, 'w')) {
            fwrite($fpOut, $iv);
            if ($fpIn = fopen($source, 'rb')) {
                while (!feof($fpIn)) {
                    $plaintext  = fread($fpIn, 16 * $this->encBlocks);
                    $ciphertext = openssl_encrypt($plaintext, $this->method, $key, OPENSSL_RAW_DATA, $iv);
                    $iv         = substr($ciphertext, 0, 16);

                    fwrite($fpOut, $ciphertext);
                }
                fclose($fpIn);
            } else {
                return 'Cant read file!';
            }

            fclose($fpOut);
        } else {
            return 'Cant save file!';
        }

        return true;
    }

    function decryptFile($source)
    {
        $key    = $this->encryptKey;
        $output = '';
        if ($fpIn = fopen($source, 'rb')) {
            $iv = fread($fpIn, 16);

            while (!feof($fpIn)) {
                $ciphertext = fread($fpIn, 16 * ($this->encBlocks + 1));
                $plaintext = openssl_decrypt($ciphertext, $this->method, $key, OPENSSL_RAW_DATA, $iv);
                $iv = substr($ciphertext, 0, 16);
                $output .= $plaintext;
            }

            fclose($fpIn);
        } else {
            return 'Cant read file!';
        }

        $basename = $this->decrypt(end(explode('/', $source)));

        header("HTTP/1.1 200 OK");
        header("Connection: close");
        header("Content-Type: application/octet-stream");
        header("Content-type: application/force-download");
        header("Accept-Ranges: bytes");
        header("Content-Disposition: Attachment; filename=" . $basename);
        echo $output;
        exit();
    }
}
