# Ranked Poll - Schulze Method for XenForo 2.3

Adds ranked-choice voting to XenForo polls using the **Schulze method** for accurate and fair election results.

## Features

- **Schulze Method**: Uses the Condorcet-compliant Schulze method to determine winners
- **Ranked Voting**: Users can rank their preferred options in order of preference
- **Partial Rankings**: Users can rank only their top choices—unranked options are treated as equal and less preferred
- **Dual Interface**:
  - Drag-and-drop interface (with JavaScript)
  - Dropdown fallback (works without JavaScript)
- **Flexible Results Display**:
  - Real-time results (updates as votes come in)
  - After-close results (only visible after poll closes)
  - Expandable detailed analysis with pairwise preference matrix
- **Backward Compatible**: Standard polls continue to work unchanged

## Installation

### Method 1: Via Admin CP (Recommended)

1. Download or clone this repository
2. Zip the `Alebarda/RankedPoll` directory
3. Go to Admin CP → Add-ons → Install add-on
4. Upload the ZIP file
5. Follow installation prompts

### Method 2: Manual Installation

1. Copy the `Alebarda/RankedPoll` folder to `src/addons/`
2. Go to Admin CP → Add-ons → Install add-on
3. Select "Install from file" and choose `Alebarda/RankedPoll`

## Database Changes

The addon makes the following database modifications:

### Extends `xf_poll` table:
- `poll_type` - Enum: 'standard' or 'ranked'
- `ranked_results_visibility` - Enum: 'realtime' or 'after_close'
- `schulze_winner_cache` - TEXT: Cached winner ID
- `schulze_matrix_cache` - MEDIUMTEXT: Cached strongest paths matrix

### Creates `xf_poll_ranked_vote` table:
- `ranked_vote_id` - Primary key
- `poll_id` - Foreign key to xf_poll
- `user_id` - Foreign key to xf_user
- `poll_response_id` - Foreign key to xf_poll_response
- `rank_position` - Position in user's ranking (1 = first choice)
- `vote_date` - Timestamp of vote

## Usage

### Creating a Ranked Poll

1. Create a new thread with a poll
2. Add your poll options (maximum 50 for ranked polls)
3. Select poll type: **Ranked-Choice Poll (Schulze Method)**
4. Choose when to show results:
   - **After Poll Closes**: Results visible only after close date
   - **Real-time**: Results update as votes come in
5. Save and post

### Voting on a Ranked Poll

**With JavaScript enabled:**
- Drag options from "Available Options" to "Your Ranking"
- Reorder by dragging within "Your Ranking"
- Remove from ranking by dragging back to "Available Options"
- Submit your vote

**Without JavaScript:**
- Use dropdown menus to assign ranks (1, 2, 3, etc.)
- Leave as "Not ranked" to skip an option
- Submit your vote

### Viewing Results

Results display:
- **Winner**: Clear indication of the Schulze winner (or tie notification)
- **Complete Ranking**: Full ranking of all options
- **Voter Count**: Total number of participants
- **Detailed Analysis** (expandable):
  - Pairwise preference matrix
  - Information about the Schulze method

## How the Schulze Method Works

The Schulze method finds the **Condorcet winner**—the option that would win in head-to-head matchups against all other options.

### Algorithm Steps:

1. **Build Preference Matrix**: Count how many voters prefer each option over every other option
2. **Calculate Strongest Paths**: Use Floyd-Warshall algorithm to find the strongest path between all pairs
3. **Determine Winner**: The option with the strongest paths to all other options wins
4. **Handle Ties**: If no clear winner emerges, report a tie

### Advantages:

- **Resistant to spoiler effect**: Adding similar candidates doesn't change outcome
- **Condorcet criterion**: Always elects the Condorcet winner if one exists
- **Strategic voting resistance**: Difficult to manipulate through tactical voting
- **Smith criterion**: Winner is always from the Smith set

## Technical Details

### File Structure

```
Alebarda/RankedPoll/
├── addon.json
├── Setup.php
├── Entity/
│   └── RankedVote.php
├── XF/
│   ├── Entity/Poll.php
│   ├── Repository/PollRepository.php
│   └── Pub/Controller/Poll.php
├── Voting/
│   └── SchulzeCalculator.php
├── _output/
│   ├── templates/
│   ├── phrases/
│   └── extension_hint.php
└── _js/
    └── alebarda/rankedpoll/
        └── voting-interface.js
```

### Performance

- **Time Complexity**: O(n³) where n = number of options
- **Recommended Limit**: 50 options per ranked poll (enforced)
- **Caching**: Schulze results are cached in database
- **Recalculation**: Triggered only on new votes or vote changes

### Dependencies

- XenForo 2.3.0+
- PHP 7.2.0+
- Optional: Sortable.js (bundled with XenForo for drag-and-drop)

## Uninstallation

**Warning**: Uninstalling will permanently delete all ranked poll data.

1. Admin CP → Add-ons → Alebarda/RankedPoll
2. Click "Uninstall"
3. Confirm deletion

This will:
- Drop the `xf_poll_ranked_vote` table
- Remove added columns from `xf_poll`
- Delete all ranked poll votes (irreversible)

Standard polls are **not affected** by uninstallation.

## Development

### Extending the Addon

The addon uses XenForo's class extension system (XFCP):

- **Poll Entity**: `\Alebarda\RankedPoll\XF\Entity\Poll`
- **PollRepository**: `\Alebarda\RankedPoll\XF\Repository\PollRepository`
- **Poll Controller**: `\Alebarda\RankedPoll\XF\Pub\Controller\Poll`

### Custom Templates

Override these templates for customization:
- `poll_block_ranked.html` - Voting interface
- `poll_results_ranked.html` - Results display

### Phrases

All user-facing text uses phrases with prefix `alebarda_rankedpoll_*`

## Support

For issues, questions, or feature requests:
- Check existing GitHub issues
- Create a new issue with detailed description
- Include XenForo version and error logs if applicable

## License

This addon is provided as-is for use with XenForo forums.

## Credits

- **Schulze Method**: Developed by Markus Schulze
- **Implementation**: Alebarda
- **XenForo**: Community forum software

---

**Version**: 1.0.0 Alpha 1
**Last Updated**: 2025-12-13
