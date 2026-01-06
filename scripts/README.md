# Utility Scripts

This directory contains utility scripts for data processing and management.

## Python Scripts

### `extract_artist_data.py`

Extracts and flattens artist data from a raw JSON dumps (specifically MusicBrainz format) into a structured CSV file.

**Features:**
- Extracts basic info: ID, Name, Country, ISNI, Genres, Aliases.
- Maps external links to specific columns (Spotify, YouTube, Instagram, etc.) based on URL patterns and relation types.
- Handles flattening of list fields (genres, aliases) into semicolon-separated strings.

**Usage:**
```bash
python3 scripts/extract_artist_data.py [input_file] [output_file]
```
- **Input:** Raw JSON-lines file (default: `artist`).
- **Output:** CSV file (default: `artist_data.csv`).

---

## PHP Scripts

### `split_csv.php`

Splits a large CSV file into smaller chunks to bypass file size limits (e.g., GitHub's 100MB limit).

**Features:**
- Reads a source CSV file line by line.
- Preserves the header row in every chunk file.
- Splits based on a target file size (default: 50MB).
- Outputs chunks to a specific directory (`requirements/data/chunks`).

**Usage:**
```bash
php scripts/split_csv.php
```
- **Configuration:** Edit the variables `$sourceFile`, `$outputDir`, and `$chunkSize` directly in the script to change defaults.
