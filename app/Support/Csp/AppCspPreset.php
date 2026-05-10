<?php

namespace App\Support\Csp;

use Spatie\Csp\Directive;
use Spatie\Csp\Keyword;
use Spatie\Csp\Policy;
use Spatie\Csp\Preset;

class AppCspPreset implements Preset
{
    public function configure(Policy $policy): void
    {
        $policy
            ->add(Directive::DEFAULT, Keyword::SELF)
            ->add(Directive::SCRIPT, [Keyword::SELF, Keyword::UNSAFE_INLINE])
            ->add(Directive::STYLE, [Keyword::SELF, Keyword::UNSAFE_INLINE])
            ->add(Directive::IMG, [Keyword::SELF, 'data:'])
            ->add(Directive::FONT, [Keyword::SELF, 'https://fonts.bunny.net'])
            ->add(Directive::CONNECT, Keyword::SELF)
            ->add(Directive::FORM_ACTION, Keyword::SELF)
            ->add(Directive::FRAME_ANCESTORS, Keyword::NONE);
    }
}
