<?php

namespace Leantime\Views\Composers;

use Leantime\Core\Composer;

/**
 *
 */

/**
 *
 */
class Entry extends Composer
{
    public static $views = [
        'global::layouts.entry',
    ];

    /**
     * @return array|string[]
     */
    /**
     * @return array|string[]
     */
    public function with()
    {
        return [
            'logoPath' => $_SESSION['companysettings.logoPath'] ?? '',
        ];
    }
}
