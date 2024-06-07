# 定义输出Markdown文件
OUTPUT_FILE="README.md"

echo "Downloading RSS feed..."
# 使用curl下载RSS XML文件内容
rss_content=$(curl -s "https://blog.yangfei.site/feed/")

# 检查是否成功下载RSS内容
if [ -z "$rss_content" ]; then
  echo "Failed to download RSS content."
  exit 1
fi

echo "Parsing RSS feed..."
# 使用xmlstarlet解析XML并提取文章标题、链接地址、发布时间
titles=$(echo "$rss_content" | xmlstarlet sel -t -m "//item/title" -v . -n)
links=$(echo "$rss_content" | xmlstarlet sel -t -m "//item/link" -v . -n)
pub_dates=$(echo "$rss_content" | xmlstarlet sel -t -m "//item/pubDate" -v . -n)

# 检查是否成功提取内容
if [ -z "$titles" ] || [ -z "$links" ] || [ -z "$pub_dates" ]; then
  echo "Failed to extract necessary data from RSS feed."
  exit 1
fi

echo "Preparing content for README.md..."
# 将提取内容按行分割并放入数组中
IFS=$'\n' read -rd '' -a title_array <<<"$titles"
IFS=$'\n' read -rd '' -a link_array <<<"$links"
IFS=$'\n' read -rd '' -a date_array <<<"$pub_dates"

echo "### Hi there 👋" > "$OUTPUT_FILE"
echo "I'm a product manager and a hobbyist developer." >> "$OUTPUT_FILE"
echo "### Latest blog posts" >> "$OUTPUT_FILE"

# 将内容格式化并写入输出文件
for i in "${!title_array[@]}"; do
  # 将日期格式化为[Y-m-d]
  formatted_date=$(date -d "${date_array[$i]}" +"%Y.%m.%d" || echo "Invalid Date")
  echo "- $formatted_date - [${title_array[$i]}](${link_array[$i]})    " >> "$OUTPUT_FILE"
done
