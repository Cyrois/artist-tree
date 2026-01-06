import json
import csv
import sys
import os
import re

# Configuration
INPUT_FILE = 'artist'
OUTPUT_FILE = 'artist_data.csv'

# Define the specific columns we want for links
PLATFORM_COLUMNS = [
    'Official Homepage',
    'Facebook',
    'Twitter',
    'Instagram',
    'YouTube',
    'Spotify',
    'Apple Music',
    'SoundCloud',
    'Bandcamp',
    'Discogs',
    'Wikidata',
    'AllMusic',
    'Deezer',
    'Tidal',
    'Last.fm',
    'Wikipedia',
    'TikTok'
]

# Mapping regex patterns to columns
# Priority: Check relation 'type' first, then fallback to URL matching if type is generic
URL_PATTERNS = {
    'facebook.com': 'Facebook',
    'twitter.com': 'Twitter',
    'x.com': 'Twitter',
    'instagram.com': 'Instagram',
    'youtube.com': 'YouTube',
    'youtu.be': 'YouTube',
    'spotify.com': 'Spotify',
    'apple.com': 'Apple Music',
    'itunes.apple.com': 'Apple Music',
    'soundcloud.com': 'SoundCloud',
    'bandcamp.com': 'Bandcamp',
    'discogs.com': 'Discogs',
    'wikidata.org': 'Wikidata',
    'allmusic.com': 'AllMusic',
    'deezer.com': 'Deezer',
    'tidal.com': 'Tidal',
    'last.fm': 'Last.fm',
    'wikipedia.org': 'Wikipedia',
    'tiktok.com': 'TikTok'
}

TYPE_MAPPING = {
    'official homepage': 'Official Homepage',
    'youtube': 'YouTube',
    'youtube music': 'YouTube', # Grouping YouTube Music with YouTube for now, or could be separate
    'discogs': 'Discogs',
    'wikidata': 'Wikidata',
    'allmusic': 'AllMusic',
    'last.fm': 'Last.fm',
    'bandcamp': 'Bandcamp',
    'soundcloud': 'SoundCloud'
}

def get_platform(rel_type, url):
    # 1. Try exact type match
    if rel_type in TYPE_MAPPING:
        return TYPE_MAPPING[rel_type]
    
    # 2. Try URL regex match for generic types (social network, streaming, etc.)
    # or if the specific type wasn't in our explicit mapping list.
    url_lower = url.lower()
    for pattern, column in URL_PATTERNS.items():
        if pattern in url_lower:
            return column
            
    return None

def extract_data(line):
    try:
        data = json.loads(line)
    except json.JSONDecodeError:
        return None

    # Basic Info
    artist_id = data.get('id', '')
    name = data.get('name', '')
    
    # Country Info (from 'area' property)
    area = data.get('area')
    country = ''
    country_codes = ''
    if area:
        country = area.get('name', '')
        country_codes = "; ".join(area.get('iso-3166-1-codes', []) or [])

    # ISNIs (list of strings)
    isnis = "; ".join(data.get('isnis', []) or [])
    
    # Genres (list of objects with 'name')
    genres_list = data.get('genres', []) or []
    genres = "; ".join([g.get('name', '') for g in genres_list])

    # Aliases (list of objects with 'name')
    aliases_list = data.get('aliases', []) or []
    aliases = "; ".join([a.get('name', '') for a in aliases_list])

    # Initialize link buckets
    links = {col: [] for col in PLATFORM_COLUMNS}

    relations = data.get('relations', [])
    for rel in relations:
        if rel.get('target-type') == 'url':
            rel_type = rel.get('type', '').lower()
            url_resource = rel.get('url', {}).get('resource', '')
            
            if not url_resource:
                continue

            platform = get_platform(rel_type, url_resource)
            
            if platform:
                links[platform].append(url_resource)

    # Build row
    row = {
        'ID': artist_id,
        'Name': name,
        'Country': country,
        'Country Code': country_codes,
        'ISNI': isnis,
        'Genres': genres,
        'Aliases': aliases
    }
    
    # Add link columns flattened
    for col in PLATFORM_COLUMNS:
        row[col] = "; ".join(links[col])

    return row

def process_file(input_path, output_path):
    if not os.path.exists(input_path):
        print(f"Error: Input file '{input_path}' not found.")
        return

    print(f"Processing '{input_path}'...")
    
    # Open output file and write header
    with open(output_path, 'w', newline='', encoding='utf-8') as csvfile:
        fieldnames = ['ID', 'Name', 'Country', 'Country Code', 'ISNI', 'Genres', 'Aliases'] + PLATFORM_COLUMNS
        writer = csv.DictWriter(csvfile, fieldnames=fieldnames)
        writer.writeheader()

        count = 0
        with open(input_path, 'r', encoding='utf-8') as f:
            for line in f:
                row = extract_data(line)
                if row:
                    writer.writerow(row)
                    count += 1
                    if count % 100000 == 0:
                        print(f"Processed {count} records...")

    print(f"Done! Extracted {count} artists to '{output_path}'.")

if __name__ == "__main__":
    # Allow command line args for files: python script.py [input] [output]
    in_file = sys.argv[1] if len(sys.argv) > 1 else INPUT_FILE
    out_file = sys.argv[2] if len(sys.argv) > 2 else OUTPUT_FILE
    
    process_file(in_file, out_file)
