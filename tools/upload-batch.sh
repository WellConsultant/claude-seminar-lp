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
git add "$BATCH_DIR"
git commit -m "Add batch upload: $TIMESTAMP" --quiet
git push origin main --quiet

URL="$RAW_BASE/$BATCH_DIR/index.txt"
HISTORY_FILE="$HOME/アップロード履歴.txt"
{
  echo "$(date '+%Y-%m-%d %H:%M:%S')  [まとめ ${INDEX} 件]  ->  $URL"
  cat "$MANIFEST" | sed 's/^/    /'
} >> "$HISTORY_FILE"

echo "$URL"
