#!/bin/bash
# check-progress.sh — Display current development progress before starting work
# Usage: scripts/check-progress.sh

PROGRESS_FILE="$(cd "$(dirname "$0")/.." && pwd)/PROGRESS.md"

if [[ ! -f "$PROGRESS_FILE" ]]; then
    echo "⚠ PROGRESS.md not found. Run the initial setup first."
    exit 1
fi

echo "═══════════════════════════════════════════════"
echo "  📦 turso-client-php — Development Progress"
echo "═══════════════════════════════════════════════"
echo ""

# Show in-progress tasks
echo "🔧 IN PROGRESS:"
in_progress=$(grep "| in_progress |" "$PROGRESS_FILE" 2>/dev/null)
if [[ -n "$in_progress" ]]; then
    echo "$in_progress" | while IFS='|' read -r _ num task priority status _; do
        num=$(echo "$num" | xargs)
        task=$(echo "$task" | xargs)
        echo "  [$num] $task"
    done
else
    echo "  (none)"
fi
echo ""

# Show pending tasks
echo "⏳ PENDING:"
pending_count=0
grep "| pending |" "$PROGRESS_FILE" 2>/dev/null | while IFS='|' read -r _ num task priority status _; do
    num=$(echo "$num" | xargs)
    task=$(echo "$task" | xargs)
    priority=$(echo "$priority" | xargs)
    echo "  [$num] $priority — $task"
    pending_count=$((pending_count + 1))
done
if [[ "$pending_count" -eq 0 ]]; then
    echo "  (all tasks in progress or completed!)"
fi
echo ""

# Show completed tasks
echo "✅ COMPLETED:"
completed=$(grep "| completed |" "$PROGRESS_FILE" 2>/dev/null)
if [[ -n "$completed" ]]; then
    echo "$completed" | while IFS='|' read -r _ num task priority status _; do
        num=$(echo "$num" | xargs)
        task=$(echo "$task" | xargs)
        echo "  [$num] $task"
    done
else
    echo "  (none yet)"
fi
echo ""

# Show recent session log
echo "📝 RECENT SESSION:"
grep -A 5 "Session:" "$PROGRESS_FILE" 2>/dev/null | tail -n 5 | sed 's/^/  /'
echo ""
echo "═══════════════════════════════════════════════"
echo "  Full tracker: PROGRESS.md"
echo "═══════════════════════════════════════════════"
