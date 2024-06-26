max_posts=5
OUTPUT_FILE="README.md"
rss_url="https://blog.yangfei.site/feed/"

echo "Downloading RSS feed..."
rss_content=$(curl -s "$rss_url")

if [ -z "$rss_content" ]; then
  echo "Failed to download RSS content."
  exit 1
fi

echo "Parsing RSS feed..."
titles=$(echo "$rss_content" | xmlstarlet sel -t -m "//item/title" -v . -n)
links=$(echo "$rss_content" | xmlstarlet sel -t -m "//item/link" -v . -n)
pub_dates=$(echo "$rss_content" | xmlstarlet sel -t -m "//item/pubDate" -v . -n)

if [ -z "$titles" ] || [ -z "$links" ] || [ -z "$pub_dates" ]; then
  echo "Failed to extract necessary data from RSS feed."
  exit 1
fi

echo "Preparing content for README.md..."
IFS=$'\n' read -rd '' -a title_array <<<"$titles"
IFS=$'\n' read -rd '' -a link_array <<<"$links"
IFS=$'\n' read -rd '' -a date_array <<<"$pub_dates"

cat > "$OUTPUT_FILE" << EOF
### Hi there 👋
I'm a product manager and a hobbyist developer.

### Latest blog posts
EOF

count=0
for i in "${!title_array[@]}"; do
  if [ $count -ge $max_posts ]; then
    break
  fi
  formatted_date=$(date -d "${date_array[$i]}" +"%Y.%m.%d" || echo "Invalid Date")
  echo "- $formatted_date - [${title_array[$i]}](${link_array[$i]})    " >> "$OUTPUT_FILE"
  ((count++))
done

current_date=$(date +"%Y.%m.%d %H:%M:%S")
echo " " >> "$OUTPUT_FILE"
echo "*- Updated on $current_date*" >> "$OUTPUT_FILE"
