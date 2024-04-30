<?php

namespace Sterc\FormIt\Hook;

class Redirect
{
    /**
     * A reference to the hook instance.
     * @var \Sterc\FormIt\Hook\ $hook
     */
    public $hook;

    /**
     * A reference to the modX instance.
     * @var \modx $modx
     */
    public $modx;

    /**
     * An array of configuration properties
     * @var array $config
     */
    public $config = [];

    /**
     * A reference to the FormIt instance.
     * @var \Sterc\FormIt $formit
     */
    public $formit;

    /**
     * @param \Sterc\FormIt\Hook $hook
     * @param array $config
     */
    public function __construct($hook, array $config = array())
    {
        $this->hook =& $hook;
        $this->formit =& $hook->formit;
        $this->modx = $hook->formit->modx;
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Redirect to a specified URL.
     *
     * Properties needed:
     * - redirectTo - Required. The ID of the resource to to redirect the user to on a successful submission.
     * - redirectParams - A JSON object of parameters to pass in the redirect URL.
     *
     * @param array $fields An array of cleaned POST fields
     *
     * @return bool False if unsuccessful.
     */
    public function process($fields = [])
    {
        if (empty($this->formit->config['redirectTo']) && empty($this->formit->config['formAction'])) {
            return false;
        }

        /** Allow external handling of the post. */
        if ($this->formit->config['formAction']) {
            $this->externalPost($fields);
        }

        $redirectParams = !empty($this->formit->config['redirectParams'])
            ? $this->formit->config['redirectParams']
            : '';
        if (!empty($redirectParams)) {
            $prefix = $this->modx->getOption('placeholderPrefix', $this->formit->config, 'fi.');
            $this->modx->setPlaceholders($fields, $prefix);
            $this->modx->parser->processElementTags('', $redirectParams, true, true);
            $redirectParams = $this->modx->fromJSON($redirectParams);
            if (empty($redirectParams)) {
                $redirectParams = '';
            }
        }
        $contextKey = $this->modx->context->get('key');
        $resource = $this->modx->getObject('modResource', $this->formit->config['redirectTo']);
        if ($resource) {
            $contextKey = $resource->get('context_key');
        }
        if (!is_numeric($this->formit->config['redirectTo']) &&
            isset($fields[$this->formit->config['redirectTo']]) &&
            is_numeric($fields[$this->formit->config['redirectTo']])
        ) {
            $url = $this->modx->makeUrl(
                $fields[$this->formit->config['redirectTo']],
                $contextKey,
                $redirectParams,
                'full'
            );
        } elseif (!is_numeric($this->formit->config['redirectTo']) &&
            substr($this->formit->config['redirectTo'], 0, 4) === 'http'
        ) {
            $url = $this->formit->config['redirectTo'];
        } else {
            $url = $this->modx->makeUrl($this->formit->config['redirectTo'], $contextKey, $redirectParams, 'full');
        }

        $this->hook->setRedirectUrl($url);

        return true;
    }

    protected function externalPost($fields = [])
    {
        $url = $this->formit->config['formAction'];
        $fields_string = http_build_query($fields);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, count($fields));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            $this->modx->log(MODx::LOG_LEVEL_ERROR, '[FormIt] failed call to external action:' . curl_error($ch));
        }

        curl_close($ch);    

        return $result;
    }
}
