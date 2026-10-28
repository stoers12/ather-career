"""Offline, deterministic maintenance tool; never called by application startup."""
from pathlib import Path
import hashlib
import json
import re
import sys

ROOT = Path(__file__).resolve().parents[2] / 'data/profile-catalog'
LAUNCH_COUNTRIES = ['JO','PS','SA','AE','QA','KW','BH','OM','IQ','LB','SY','YE','EG']
def encoded(value):
    return (json.dumps(value, ensure_ascii=False, indent=2) + '\n').encode('utf-8')

def build():
    provenance = json.loads((ROOT/'provenance.json').read_text(encoding='utf-8'))
    for source in provenance['files']:
        assert hashlib.sha256((ROOT/source['path']).read_bytes()).hexdigest() == source['sha256'], source['path']
    rows = json.loads((ROOT/'sources/iso-3166-1.json').read_text(encoding='utf-8'))['3166-1']
    codes = sorted(row['alpha_2'] for row in rows)
    assert len(codes) == len(set(codes)) == 249
    assert all(re.fullmatch('[A-Z]{2}', code) for code in codes)
    labels = {language: json.loads((ROOT/f'sources/cldr-{language}.json').read_text(encoding='utf-8'))['main'][language]['localeDisplayNames']['territories'] for language in ['ar','en']}
    assert provenance['iso_source_country_count'] == len(codes)
    assert provenance['country_count'] == len(LAUNCH_COUNTRIES) == 13
    assert provenance['supported_country_codes'] == LAUNCH_COUNTRIES
    assert len(set(LAUNCH_COUNTRIES)) == 13 and set(LAUNCH_COUNTRIES) <= set(codes)
    countries = {code:{language:labels[language][code] for language in ['ar','en']} for code in LAUNCH_COUNTRIES}
    assert all(name and name.strip() for entry in countries.values() for name in entry.values())
    assert not set(codes).intersection(['ZZ','XK','EU','UN','UK','AC','CP','CQ','DG','EA','EZ','IC','QO','TA','XA','XB','SU','AN','BU','TP','YU'])
    choices = json.loads((ROOT/'choices.json').read_text(encoding='utf-8'))
    assert set(choices['languages']) == {'ar','en'}
    language_labels = {locale: json.loads((ROOT/f'sources/cldr-{locale}-languages.json').read_text(encoding='utf-8'))['main'][locale]['localeDisplayNames']['languages'] for locale in ['ar','en']}
    assert choices['languages'] == {code:{locale:language_labels[locale][code] for locale in ['ar','en']} for code in ['ar','en']}
    assert set(choices['statuses']) == {'student','graduate','employed','self_employed','seeking_work','other'}
    result = {'revision':provenance['revision'],'countries':countries,**choices}
    output=encoded(result)
    assert hashlib.sha256(output).hexdigest() == provenance['generated_sha256'], 'Reviewed output checksum differs'
    return output

if __name__ == '__main__':
    result=build(); destination=ROOT/'catalog.json'
    if '--check' in sys.argv:
        assert destination.read_bytes() == result, 'Catalog differs from pinned sources/choices'
    else:
        destination.write_bytes(result)
    print('PASS deterministic product catalog: 13 launch countries in approved order from 249 ISO source entries, complete AR/EN labels, 2 languages, 6 statuses; SHA256 '+hashlib.sha256(result).hexdigest())
