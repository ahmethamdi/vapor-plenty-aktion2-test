<?php

namespace VaporAktion2\Containers;

use Plenty\Plugin\Templates\Twig;

/**
 * DataProvider (plugin.json » dataProviders): plentyShop LTS container'ına bağlanır.
 */
class GeschenkBoxVorschauContainer
{
    public function call(Twig $twig, $arg = null): string
    {
        return $twig->render('VaporAktion2::Container.Box', ['ort' => 'vorschau']);
    }
}
