@php($variant = $variant ?? 'aliases')

@switch($variant)
    @case('aliases')
        Search aliases: <code>source:</code>, <code>res:</code>, <code>rg:</code>, <code>sub:</code>.
        @break

    @case('examples')
        Try <code>source:web-dl</code>, <code>res:1080p</code>, <code>rg:&lt;release-group&gt;</code>, or <code>sub:&lt;language&gt;</code>.
        @break

    @case('start')
        Start with aliases like <code>source:web-dl</code>, <code>res:1080p</code>, <code>rg:&lt;release-group&gt;</code>, or <code>sub:&lt;language&gt;</code>.
        @break
@endswitch
