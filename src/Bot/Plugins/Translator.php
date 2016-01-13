<?php
namespace Bot\Plugins;

use Bot\Plugins\Infrastructure\Plugin;
use Yandex\Translate\Translator as YTranslator;

class Translator extends Plugin
{
    public function init()
    {
        $translator = new YTranslator($this->pluginConfig['yandex']['translator']['key']);
        $this->container->set('translator', $translator);
    }

    public function getTranslation($translationSubject, $direction = 'ru')
    {
        $direction = $direction ?: 'ru';
        try {
            $translation = $this->container->get('translator')->translate($translationSubject, $direction);
            $result = (string)$translation;

            return (string)$result;

        } catch (\Exception $e) {
            \Util::console($e->getMessage());
        }

        return null;
    }
}
