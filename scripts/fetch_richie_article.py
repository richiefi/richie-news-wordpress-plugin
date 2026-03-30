#!/usr/bin/env python3
"""Fetch Richie article HTML and assets into a local folder."""

import argparse
import json
import sys
from pathlib import Path
from typing import Any, Dict, Iterable, Optional, Tuple
from urllib.error import URLError
from urllib.parse import urlencode, urljoin
from urllib.request import Request, urlopen

DEFAULT_BASE_URL = "http://localhost:10004"
DEFAULT_TOKEN = "testing"
DEFAULT_ASSETS_ENDPOINT = "/wp-json/richie/v1/assets"
DEFAULT_ARTICLE_ENDPOINT = "/wp-json/richie/v1/article"


def build_url(base_url: str, path: str, params: Dict[str, Any]) -> str:
    base = base_url.rstrip("/")
    full_path = f"{base}{path}"
    if not params:
        return full_path
    return f"{full_path}?{urlencode(params)}"


def fetch_json(url: str) -> Dict[str, Any]:
    request = Request(url, headers={"Accept": "application/json"})
    with urlopen(request, timeout=30) as response:
        data = response.read()
    try:
        return json.loads(data)
    except json.JSONDecodeError as exc:
        raise ValueError(f"Failed to decode JSON from {url}") from exc


def safe_output_path(root: Path, relative_path: str) -> Path:
    relative = relative_path.lstrip("/")
    output_path = (root / relative).resolve()
    root_resolved = root.resolve()
    if root_resolved not in output_path.parents and output_path != root_resolved:
        raise ValueError(f"Refusing to write outside output folder: {relative_path}")
    return output_path


def download_file(url: str, dest: Path) -> bool:
    dest.parent.mkdir(parents=True, exist_ok=True)
    try:
        request = Request(url, headers={"User-Agent": "richie-feed-fetcher"})
        with urlopen(request, timeout=60) as response, dest.open("wb") as handle:
            handle.write(response.read())
        return True
    except URLError as exc:
        print(f"Failed to download {url}: {exc}", file=sys.stderr)
        return False


def collect_photo_items(photos: Any) -> Iterable[Tuple[str, Optional[str]]]:
    if not isinstance(photos, list):
        return []
    items = []
    for group in photos:
        if not isinstance(group, list):
            continue
        for photo in group:
            if not isinstance(photo, dict):
                continue
            local_name = photo.get("local_name")
            remote_url = photo.get("remote_url")
            if local_name:
                items.append((local_name, remote_url))
    return items


def normalize_remote_url(base_url: str, local_name: str) -> str:
    local_path = local_name.lstrip("/")
    return urljoin(f"{base_url.rstrip('/')}/", local_path)


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Fetch Richie article HTML and assets.")
    parser.add_argument("article_id", help="Richie article ID")
    parser.add_argument(
        "--base-url",
        default=DEFAULT_BASE_URL,
        help=f"Base URL for the site (default: {DEFAULT_BASE_URL})",
    )
    parser.add_argument(
        "--token",
        default=DEFAULT_TOKEN,
        help="Token query param for the article endpoint",
    )
    parser.add_argument(
        "--output",
        default=None,
        help="Output directory (default: ./article-<id>)",
    )
    parser.add_argument(
        "--template",
        default=None,
        help="Template variation name for article rendering (default: 'article', use 'block' to force block template)",
    )
    parser.add_argument(
        "--assets-endpoint",
        default=DEFAULT_ASSETS_ENDPOINT,
        help=f"Assets feed endpoint (default: {DEFAULT_ASSETS_ENDPOINT})",
    )
    parser.add_argument(
        "--article-endpoint",
        default=DEFAULT_ARTICLE_ENDPOINT,
        help=f"Article feed endpoint (default: {DEFAULT_ARTICLE_ENDPOINT})",
    )
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    article_id = str(args.article_id)
    is_default_output = args.output is None
    output_dir = Path(args.output or f"article-{article_id}")

    if output_dir.exists() and any(output_dir.iterdir()) and not is_default_output:
        print(f"Output directory is not empty: {output_dir}", file=sys.stderr)
        return 2

    output_dir.mkdir(parents=True, exist_ok=True)

    article_params = {
        "token": args.token,
    }
    if args.template:
        article_params["template"] = args.template
    assets_params = {}

    article_url = build_url(
        args.base_url,
        f"{args.article_endpoint.rstrip('/')}/{article_id}",
        article_params,
    )
    assets_url = build_url(args.base_url, args.assets_endpoint, assets_params)

    print(f"Fetching article: {article_url}")
    article_data = fetch_json(article_url)

    print(f"Fetching assets: {assets_url}")
    assets_data = fetch_json(assets_url)

    content_html = article_data.get("content_html_document")
    if not content_html:
        raise ValueError("content_html_document not found in article response")

    index_path = output_dir / "index.html"
    index_path.write_text(content_html, encoding="utf-8")

    downloads = 0
    failures = 0

    for local_name, remote_url in collect_photo_items(article_data.get("photos")):
        source_url = remote_url or normalize_remote_url(args.base_url, local_name)
        dest_path = safe_output_path(output_dir, local_name)
        if download_file(source_url, dest_path):
            downloads += 1
        else:
            failures += 1

    article_assets = article_data.get("assets", [])
    if isinstance(article_assets, list):
        for asset in article_assets:
            if not isinstance(asset, dict):
                continue
            local_name = asset.get("local_name")
            remote_url = asset.get("remote_url")
            if not local_name or not remote_url:
                continue
            dest_path = safe_output_path(output_dir, local_name)
            if download_file(remote_url, dest_path):
                downloads += 1
            else:
                failures += 1

    app_assets = assets_data.get("app_assets", [])
    if isinstance(app_assets, list):
        for asset in app_assets:
            if not isinstance(asset, dict):
                continue
            local_name = asset.get("local_name")
            remote_url = asset.get("remote_url")
            if not local_name or not remote_url:
                continue
            dest_path = safe_output_path(output_dir, local_name)
            if download_file(remote_url, dest_path):
                downloads += 1
            else:
                failures += 1

    print(f"Saved {index_path}")
    print(f"Downloaded files: {downloads}")
    if failures:
        print(f"Failed downloads: {failures}", file=sys.stderr)

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
