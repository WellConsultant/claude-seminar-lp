#!/bin/bash
# ファイルを claude-seminar-lp リポジトリの uploads/ に追加してGitHubへpushする

set -e

REPO_DIR="$HOME/.claude/tools/claude-seminar-lp-uploads"
SRC="${1:-}"
MAX_BYTES=99614720

if [ -z "$SRC" ]; then
  echo "使い方: upload-image.sh <ファイルのパス>" >&2
  exit 1
fi

if [ ! -f "$SRC" ]; then
  echo "エラー: ファイルが見つかりません: $SRC" >&2
  exit 1
fi

EXT="${SRC##*.}"
EXT_LOWER=$(printf '%s' "$EXT" | tr '[:upper:]' '[:lower:]')
case "$EXT_LOWER" in
  jpg|jpeg|png|gif|webp|svg|pdf|mp4|mov|m4v|html|htm) ;;
  *)
    echo "エラー: 対応拡張子は jpg/jpeg/png/gif/webp/svg/pdf/mp4/mov/m4v/html/htm のみです（受け取ったのは .$EXT）" >&2
    exit 1
    ;;
esac

FILE_SIZE=$(stat -f%z "$SRC")
if [ "$FILE_SIZE" -gt "$MAX_BYTES" ]; then
  SIZE_MB=$((FILE_SIZE / 1024 / 1024))
  echo "エラー: このファイルは約${SIZE_MB}MBあります。GitHubの上限を安全に下回る95MB以下にしてください。" >&2
  exit 1
fi

if [ ! -d "$REPO_DIR/.git" ]; then
  echo "エラー: アップロード用Gitリポジトリが見つかりません: $REPO_DIR" >&2
  exit 1
fi

BASE_NAME=$(basename "$SRC")
SHORT_CODE=$(openssl rand -hex 3)
FILENAME="${SHORT_CODE}.${EXT_LOWER}"
DEST="$REPO_DIR/uploads/$FILENAME"

mkdir -p "$REPO_DIR/uploads"
cp "$SRC" "$DEST"

cd "$REPO_DIR"

# コミット前にリモートの最新へ確実に合わせる。
# これをしないと、他の端末やGitHub上での更新があったとき push が
# non-fast-forward で弾かれる。rebase の取り残しもここで掃除する。
# アップロード対象は未追跡ファイルなので reset --hard では消えない。
rm -rf .git/rebase-merge .git/rebase-apply
git fetch origin main --quiet
git reset --hard FETCH_HEAD --quiet

START_HEAD=$(git rev-parse HEAD)

git add "uploads/$FILENAME"
git commit -m "Add uploaded file: $FILENAME" --quiet

if ! git push origin main --quiet; then
  # fetch から push までの間に更新が入った場合、一度だけ取り込み直して再送する
  git fetch origin main --quiet
  git rebase FETCH_HEAD --quiet || git rebase --abort 2>/dev/null || true
  if ! git push origin main --quiet; then
    git reset --mixed "$START_HEAD" --quiet
    rm -f "$DEST"
    echo "エラー: GitHubへの送信に失敗しました。今回の送信待ちは自動的に解除しました。" >&2
    exit 1
  fi
fi

RAW_URL="https://raw.githubusercontent.com/WellConsultant/claude-seminar-lp/main/uploads/$FILENAME"

case "$EXT_LOWER" in
  html|htm)
    URL="https://htmlpreview.github.io/?$RAW_URL"
    ;;
  *)
    URL="$RAW_URL"
    ;;
esac

HISTORY_FILE="$HOME/アップロード履歴.txt"
echo "$(date '+%Y-%m-%d %H:%M:%S')  $BASE_NAME  ->  $URL" >> "$HISTORY_FILE"

echo "$URL"
