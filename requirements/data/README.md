# Artist Data Extraction

This dataset contains detailed information extracted from the MusicBrainz `artist` database.

## File Format
*   **Filename**: `artist_data.csv`
*   **Format**: CSV (Comma-Separated Values)
*   **Encoding**: UTF-8

## Columns

| Column Name | Description |
| :--- | :--- |
| **ID** | The unique MusicBrainz UUID for the artist. |
| **Name** | The primary name of the artist or group. |
| **Country** | The full name of the country associated with the artist (from the `area` field). |
| **Country Code** | The ISO 3166-1 country code(s) (e.g., `US`, `GB`). Multiple codes are separated by `; `. |
| **ISNI** | International Standard Name Identifier(s). Multiple values are separated by `; `. |
| **Genres** | List of genres associated with the artist. Multiple values are separated by `; `. |
| **Aliases** | Alternative names, legal names, or search hints for the artist. Multiple values are separated by `; `. |
| **Official Homepage** | URL(s) to the artist's official website. |
| **Facebook** | Link to the artist's Facebook profile. |
| **Twitter** | Link to the artist's Twitter (X) profile. |
| **Instagram** | Link to the artist's Instagram profile. |
| **YouTube** | Link to the artist's YouTube channel (including YouTube Music). |
| **Spotify** | Link to the artist's Spotify profile. |
| **Apple Music** | Link to the artist's Apple Music / iTunes page. |
| **SoundCloud** | Link to the artist's SoundCloud profile. |
| **Bandcamp** | Link to the artist's Bandcamp page. |
| **Discogs** | Link to the artist's Discogs entry. |
| **Wikidata** | Link to the artist's Wikidata item. |
| **AllMusic** | Link to the artist's AllMusic profile. |
| **Deezer** | Link to the artist's Deezer profile. |
| **Tidal** | Link to the artist's Tidal profile. |
| **Last.fm** | Link to the artist's Last.fm profile. |
| **Wikipedia** | Link to the artist's Wikipedia page. |
| **TikTok** | Link to the artist's TikTok profile. |

## Parsing Instructions

### Multi-Value Fields
Several columns (Country Code, ISNI, Genres, Aliases, and all Link columns) may contain multiple values. These are separated by a semicolon and a space:
`value1; value2; value3`

**Example (Python):**
```python
import csv

with open('artist_data.csv', 'r', encoding='utf-8') as f:
    reader = csv.DictReader(f)
    for row in reader:
        # Split genres into a list
        genres = row['Genres'].split('; ') if row['Genres'] else []
        
        # Split aliases into a list
        aliases = row['Aliases'].split('; ') if row['Aliases'] else []
        
        print(f"Artist: {row['Name']}, Genres: {genres}")
```

### Handling Large Files
The file contains over 2.7 million records. When parsing, it is recommended to process the file line-by-line (stream) rather than loading the entire file into memory at once.
