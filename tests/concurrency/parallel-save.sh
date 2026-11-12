#!/usr/bin/env bash
# Fires N simultaneous "save new protocol" requests at the running dev stack, as N different
# browser sessions would, and reports how many protocols were actually stored.
# Usage: tests/concurrency/parallel-save.sh [N] [base-url]
set -uo pipefail
N=${1:-20}; BASE=${2:-http://localhost:8080}
WORK=$(mktemp -d); trap 'rm -rf "$WORK"' EXIT
YEAR=$(date +%Y); TODAY=$(date +%d/%m/%Y)
TAG="parallel-$(date +%s)"

login() {  # $1 = session dir
  curl -s -c "$1/c" -b "$1/c" "$BASE/login" -o "$1/login.html"
  local t; t=$(grep -o 'name="_token" value="[^"]*"' "$1/login.html" | head -1 | sed 's/.*value="//;s/"//')
  curl -s -c "$1/c" -b "$1/c" -o /dev/null --data-urlencode "_token=$t" -d "username=admin&password=password" "$BASE/login"
  curl -s -c "$1/c" -b "$1/c" "$BASE/home" -o "$1/form.html"
}

for i in $(seq 1 "$N"); do mkdir -p "$WORK/$i"; login "$WORK/$i" & done; wait

for i in $(seq 1 "$N"); do
  (
    d="$WORK/$i"
    tok=$(grep -o 'name="_token" value="[^"]*"' "$d/form.html" | head -1 | sed 's/.*value="//;s/"//')
    num=$(grep -o 'name="protocolnum_proposed" value="[0-9]*"' "$d/form.html" | grep -o '[0-9]*$')
    [ -z "$num" ] && num=$(grep -o 'id="protocolnum"[^>]*' "$d/form.html" | head -1; grep -A3 'name="protocolnum"' "$d/form.html" | grep -o 'value="[0-9]*"' | head -1 | grep -o '[0-9]*')
    curl -s -b "$d/c" -o "$d/resp.html" -w "%{http_code} %{redirect_url}\n" \
      --data-urlencode "_token=$tok" -d "protocolnum=$num&protocolnum_proposed=$num" \
      --data-urlencode "protocoldate=$TODAY" -d "etos=$YEAR" --data-urlencode "thema=$TAG #$i" \
      -d "fakelos=&in_num=&in_date=&in_topos_ekdosis=&in_arxi_ekdosis=&in_paraliptis=&diekp_eos=&in_perilipsi=&out_date=&diekp_date=&sxetiko=&out_to=&out_perilipsi=&keywords=&paratiriseis=&keep=&file_inputs_count=1&ada1=&ada2=&ada3=&sendEmailTo=&reply_to_email=" \
      "$BASE/home" > "$d/status"
  ) &
done; wait

echo "Requests sent:        $N (all proposing the same next number)"
echo "HTTP results:         $(cat "$WORK"/*/status | awk '{print $1}' | sort | uniq -c | tr '\n' ' ')"
echo "Redirected to saved protocol: $(grep -c '/home/[0-9]' "$WORK"/*/status | awk -F: '{s+=$2} END{print s}')"
echo "Redirected with error:        $(grep -c '/home$' "$WORK"/*/status | awk -F: '{s+=$2} END{print s}')"
echo "TAG=$TAG"
