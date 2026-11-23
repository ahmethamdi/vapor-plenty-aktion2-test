<?php

namespace VaporAktion2Test\Containers;

use Plenty\Plugin\Templates\Twig;

/**
 * DataProvider (plugin.json » dataProviders): plentyShop LTS container'ına bağlanır.
 */
class GeschenkBoxVorschauContainer
{
    public function call(Twig $twig, $arg = null): string
    {
        return $twig->render('VaporAktion2Test::Container.Box', ['ort' => 'vorschau']);
    }
}
