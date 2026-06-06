# Search Alias Contract

The browse search box supports a small set of metadata aliases that are parsed out of `q` before browse filters are applied. This contract is intended to be reused by Browse, Saved Views, RSS Presets, Watch Presets, and future Discovery surfaces.

## Supported aliases

All aliases are case-insensitive.

- `rg:<value>` maps to release group
- `source:<value>` maps to source
- `res:<value>` maps to resolution
- `lang:<value>` maps to language
- `audio:<value>` maps to audio language
- `sub:<value1,value2>` maps to subtitle language and preserves comma-separated OR intent

## Behavior

- Unknown `key:value` tokens stay in `q` unchanged so free-text search is preserved.
- Explicit query parameters win over aliases found in `q` and should not be overwritten unexpectedly.
- `sub:` is the only subtitle alias. There is no `subs:` alias.

## Examples

- `q=Matrix RG:NTB res:2160p`
- `q=Matrix SoUrCe:BLURAY SuB:danish,english`
- `q=Spider-Man lang:english audio:japanese sub:danish,english`
- `q=Docu foo:bar` keeps `foo:bar` in the free-text query

## Notes

- `sub:danish,english` should be interpreted as OR intent across the comma-separated values.
- This contract is deliberately small. It should not be expanded with new aliases or UI affordances as part of this slice.
