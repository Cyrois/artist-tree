# Artist Tree - Product Overview

Artist Tree is a festival lineup builder and artist discovery platform that helps music festival organizers create balanced lineups using data-driven insights from Spotify and YouTube APIs.

## Core Features

- **Artist Discovery**: Search and import artists from Spotify with metadata enrichment
- **Data-Driven Scoring**: Calculate artist scores based on configurable metrics (Spotify popularity, monthly listeners, YouTube subscribers)
- **Lineup Builder**: Create festival lineups with tier-based organization (headliners, sub-headliners, etc.)
- **Multi-Organization Support**: Different organizations can have custom metric weights and scoring preferences
- **Real-time Analytics**: Track artist performance metrics over time

## Key Business Logic

- Artists are scored using weighted metrics that can be customized per organization
- Lineup tiers are automatically calculated but can be manually overridden
- External API data (Spotify/YouTube) is cached and refreshed asynchronously
- The system supports multi-tenancy with organization-level customization

## Target Users

Music festival organizers, booking agents, and event planners who need data-driven insights for artist selection and lineup curation.