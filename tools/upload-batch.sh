#!/bin/bash
# 複数ファイルをまとめて claude-seminar-lp リポジトリにアップし、
# 一覧(index.txt)への1本のリンクを返す。
# 使い方: tools/upload-batch.sh <ファイル1> <ファイル2> ...

set -e

REPO_DIR="$HOME/.claude/tools/claude-seminar-lp-uploads"
RAW_BASE="https://raw.githubusercontent.com/WellConsultant/claude-seminar-lp/main"

if [ "$#" -eq 0 ]; then
  echo "使い方: upload-batch.sh <ファイル1> <ファイル2> ..." >&2
  exit 1
fi

TIMESTAMP=$(date +%Y%m%d-%H%M%S)
BATCH_CODE=$(openssl rand -hex 3)
BATCH_DIR="uploads/b-$BATCH_CODE"
mkdir -p "$REPO_DIR/$BATCH_DIR"
MANIFEST="$REPO_DIR/$BATCH_DIR/index.txt"
: > "$MANIFEST"

INDEX=0
for SRC in "$@"; do
  BASE_NAME=$(basename "$SRC")
  if [ ! -f "$SRC" ]; then
    echo "スキップ（ファイルが見つかりません）: $BASE_NAME" >> "$MANIFEST"
    continue
  fi
  EXT="${SRC##*.}"
  EXT_LOWER=$(echo "$EXT" | tr '[:upper:]' '[:lower:]')
  case "$EXT_LOWER" in
    jpg|jpeg|png|gif|webp|svg|pdf|mp4|mov|m4v|html|htm) ;;
    *)
      echo "スキップ（非対応拡張子 .$EXT）: $BASE_NAME" >> "$MANIFEST"
      continue
      ;;
  esac
  INDEX=$((INDEX+1))
  UNIQUE_NAME="$(openssl rand -hex 3).${EXT_LOWER}"
  cp "$SRC" "$REPO_DIR/$BATCH_DIR/$UNIQUE_NAME"
  RAW_URL="$RAW_BASE/$BATCH_DIR/$UNIQUE_NAME"
  case "$EXT_LOWER" in
    html|htm) FILE_URL="https://htmlpreview.github.io/?$RAW_URL" ;;
    *) FILE_URL="$RAW_URL" ;;
  esac
  echo "$BASE_NAME  ->  $FILE_URL" >> "$MANIFEST"
done

if [ "$INDEX" -eq 0 ]; then
  echo "エラー: アップロードできたファイルが1つもありませんでした（対応拡張子: jpg/jpeg/png/gif/webp/svg/pdf/mp4/mov/m4v/html/htm）" >&2
  cat "$MANIFEST" >&2
  exit 1
fi

cd "$REPO_DIR"

# リモートの最新状態へ強制的に合わせる（upload-image.sh と同じ方式）。
# merge / rebase / pull を使わないため、競合で途中停止して詰まることがない。
sync_to_remote() {
  rm -rf .git/rebase-merge .git/rebase-apply
  rm -f .git/MERGE_HEAD .git/CHERRY_PICK_HEAD .git/index.lock
  git fetch origin main --quiet
  git reset --hard FETCH_HEAD --quiet
  git symbolic-ref -q HEAD >/dev/null 2>&1 || git checkout -q -B main
}

# この時点では $BATCH_DIR は未追跡なので reset --hard では消えない
sync_to_remote

git add "$BATCH_DIR"
git commit -m "Add batch upload: $TIMESTAMP" --quiet

if ! git push origin main --quiet 2>/dev/null; then
  # コミット済みなので $BATCH_DIR は reset --hard で消える。退避してから作り直す
  TMP_BATCH=$(mktemp -d)
  cp -R "$REPO_DIR/$BATCH_DIR/." "$TMP_BATCH/"
  sync_to_remote
  mkdir -p "$REPO_DIR/$BATCH_DIR"
  cp -R "$TMP_BATCH/." "$REPO_DIR/$BATCH_DIR/"
  rm -rf "$TMP_BATCH"

  git add "$BATCH_DIR"
  git commit -m "Add batch upload: $TIMESTAMP" --quiet

  if ! git push origin main --quiet; then
    sync_to_remote
    echo "エラー: GitHubへの送信に失敗しました。今回の送信待ちは自動的に解除しました。" >&2
    exit 1
  fi
fi

URL="$RAW_BASE/$BATCH_DIR/index.txt"
HISTORY_FILE="$HOME/アップロード履歴.txt"
{
  echo "$(date '+%Y-%m-%d %H:%M:%S')  [まとめ ${INDEX} 件]  ->  $URL"
  cat "$MANIFEST" | sed 's/^/    /'
} >> "$HISTORY_FILE"

echo "$URL"
