# Artist Data Import Process

This directory contains the split CSV files for the initial bulk import of ~2.7 million artists.

## Data Ingestion Workflow

To import this data and correctly handle duplicate Spotify IDs (which occur when multiple MusicBrainz IDs map to the same Spotify artist), follow this 3-step process.

### Step 1: Initial Bulk Import
Run the high-performance bulk import command. This command is optimized for memory and speed but will skip artists that share a Spotify ID with an existing record.

```bash
php artisan artist:import-csv requirements/data/artist_data/artist_data_part_*.csv
```

*Note: For the initial large-scale import, it is recommended to temporarily modify `ImportArtistsFromCsvCommand.php` to log conflicts to a CSV file (e.g., `storage/app/import_conflicts.csv`) for reconciliation.*

### Step 2: Reconcile "Safe" Conflicts
For records that were skipped due to Spotify ID collisions, many are simple name variations (e.g., "James Cotton" vs "James Cotton Blues Band"). These should be merged as aliases.

A reconciliation command should:
1. Read the conflict log.
2. Use `similar_text()` or substring matching to compare the existing name with the skipped name.
3. If similarity is > 70%, add the skipped name as an `ArtistAlias` for the existing artist.

### Step 3: Resolve Ambiguous Conflicts
Records with low name similarity (e.g., "Randy Edelman" vs "Trevor Jones" sharing a Spotify ID) require an authoritative source of truth.

A resolution command should:
1. Query the Spotify API (`/v1/artists/{id}`) for the "ambiguous" Spotify ID.
2. Update the `artists.name` in the database to the **official stage name** returned by Spotify.
3. Move both the old database name and the conflicting CSV name into the `artist_aliases` table.

## Conflict Resolution Summary (Jan 2026)
- **Total Records Processed:** ~2,770,000
- **Total Conflicts Identified:** 1,884
- **Automatically Reconciled:** 1,543 (Fuzzy matching)
- **Spotify API Resolved:** 341 (Authoritative rename)

---
*Note: The reconciliation and Spotify resolution scripts used for the initial import were temporary utility commands and are not part of the core application logic.*
