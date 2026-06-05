#!/usr/bin/env python3
import argparse
import datetime as dt
import html
import http.cookiejar
import json
import re
import sys
import urllib.parse
import urllib.request
from pathlib import Path


TOURNAMENT_ID = "D6F7A756-39F3-4CB4-8F27-0E30E0421F4A"
BASE_URL = "https://dbv.turnier.de"
DEFAULT_TEAMS = {
    "710": "SC Gremmendorf 1",
    "758": "SC Gremmendorf 2",
    "811": "SC Gremmendorf 3",
    "861": "SC Gremmendorf J1",
}


def fetch(opener, url):
    request = urllib.request.Request(
        url,
        headers={
            "User-Agent": (
                "Mozilla/5.0 (X11; Linux x86_64) "
                "AppleWebKit/537.36 Chrome/125 Safari/537.36"
            ),
            "Accept-Language": "de-DE,de;q=0.9,en;q=0.8",
        },
    )
    with opener.open(request, timeout=30) as response:
        return response.read().decode("utf-8", errors="replace")


def accept_cookie_wall(opener, return_url):
    data = urllib.parse.urlencode(
        {
            "ReturnUrl": return_url,
            "SettingsOpen": "false",
            "CookiePurposes": "1",
        }
    ).encode("utf-8")
    request = urllib.request.Request(
        f"{BASE_URL}/cookiewall/Save",
        data=data,
        headers={
            "User-Agent": (
                "Mozilla/5.0 (X11; Linux x86_64) "
                "AppleWebKit/537.36 Chrome/125 Safari/537.36"
            ),
            "Content-Type": "application/x-www-form-urlencoded",
        },
    )
    with opener.open(request, timeout=30) as response:
        return response.read().decode("utf-8", errors="replace")


def fetch_team_matches(opener, team_id):
    return_url = f"/sport/teammatches.aspx?id={TOURNAMENT_ID}&tid={team_id}"
    url = f"{BASE_URL}{return_url}"
    page = fetch(opener, url)
    if "cookiewall/Save" in page or "message-page__modal" in page:
        page = accept_cookie_wall(opener, return_url)
    return page


def strip_tags(value):
    value = re.sub(r"<br\s*/?>", " ", value, flags=re.I)
    value = re.sub(r"<[^>]+>", " ", value)
    value = html.unescape(value)
    return re.sub(r"\s+", " ", value).strip()


def extract_cells(row_html):
    cells = re.findall(r"<td\b[^>]*>(.*?)</td>", row_html, flags=re.I | re.S)
    return [strip_tags(cell) for cell in cells]


def extract_match_url(row_html):
    match = re.search(r'href="([^"]*teammatch\.aspx\?[^"]+)"', row_html, flags=re.I)
    if not match:
        return ""
    return urllib.parse.urljoin(f"{BASE_URL}/sport/", html.unescape(match.group(1)))


def parse_german_datetime(value):
    match = re.search(r"(\d{2})\.(\d{2})\.(\d{4})\s+(\d{2}):(\d{2})", value)
    if not match:
        return None
    day, month, year, hour, minute = map(int, match.groups())
    return dt.datetime(year, month, day, hour, minute, tzinfo=dt.timezone(dt.timedelta(hours=2)))


def parse_matches(page):
    table_match = re.search(
        r'<table class="[^"]*\bmatches\b[^"]*">(.*?)</table>',
        page,
        flags=re.I | re.S,
    )
    if not table_match:
        return []

    matches = []
    for row_html in re.findall(r"<tr\b[^>]*>(.*?)</tr>", table_match.group(1), flags=re.I | re.S):
        cells = extract_cells(row_html)
        if len(cells) < 12 or not re.search(r"\d{2}\.\d{2}\.\d{4}", cells[1]):
            continue

        planned_at = parse_german_datetime(cells[1])
        if not planned_at:
            continue

        matches.append(
            {
                "datetime": planned_at,
                "league": cells[2],
                "home": cells[6],
                "away": cells[8],
                "result": cells[9],
                "location": cells[11],
                "url": extract_match_url(row_html),
            }
        )
    return matches


def is_gremmendorf_match(match):
    return "gremmendorf" in f"{match['home']} {match['away']}".lower()


def next_match(matches, now):
    future_matches = [
        match
        for match in matches
        if match["datetime"] >= now and is_gremmendorf_match(match)
    ]
    if not future_matches:
        return None
    return sorted(future_matches, key=lambda match: match["datetime"])[0]


def main():
    parser = argparse.ArgumentParser(description="Aktualisiert fixtures.json aus DBV-Teamspielplänen.")
    parser.add_argument("--out", default="fixtures.json", help="Zieldatei, Standard: fixtures.json")
    parser.add_argument("--today", help="Testdatum im Format YYYY-MM-DD")
    args = parser.parse_args()

    now = dt.datetime.now(dt.timezone(dt.timedelta(hours=2)))
    if args.today:
        today = dt.date.fromisoformat(args.today)
        now = dt.datetime.combine(today, dt.time.min, tzinfo=dt.timezone(dt.timedelta(hours=2)))

    cookie_jar = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cookie_jar))

    teams = {}
    errors = {}
    for team_id, team_name in DEFAULT_TEAMS.items():
        try:
            page = fetch_team_matches(opener, team_id)
            match = next_match(parse_matches(page), now)
            if not match:
                continue
            teams[team_id] = {
                "teamName": team_name,
                "datetime": match["datetime"].isoformat(),
                "home": match["home"],
                "away": match["away"],
                "location": match["location"],
                "url": match["url"],
            }
        except Exception as exc:
            errors[team_id] = str(exc)

    payload = {
        "updatedAt": dt.datetime.now(dt.timezone.utc).isoformat(),
        "source": BASE_URL,
        "teams": teams,
    }
    if errors:
        payload["errors"] = errors

    out_path = Path(args.out)
    out_path.write_text(json.dumps(payload, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

    print(f"Wrote {out_path} with {len(teams)} team fixture(s).")
    if errors:
        print(f"Warnings: {len(errors)} team(s) failed.", file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
