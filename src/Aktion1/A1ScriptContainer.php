<?php

namespace VaporAktion2Test\Aktion1;

use Plenty\Plugin\Templates\Twig;

/**
 * DataProvider, Aktion 1: aktion1.js/css yükler (Ceres::Script.AfterScriptsLoaded).
 */
class A1ScriptContainer
{
    public function call(Twig $twig, $arg = null): string
    {
        return $twig->render('VaporAktion2Test::Aktion1.Script', []);
    }
}
