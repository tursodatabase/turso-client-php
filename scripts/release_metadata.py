import os
import sys
import argparse
import requests


def upload_release_asset(token, release_id, actor, file_path):
    # Retrieve the GITHUB_TOKEN from the environment
    token = token or os.getenv("GITHUB_TOKEN")
    if not token:
        print("GITHUB_TOKEN environment variable is not set.")
        sys.exit(1)

    headers = {
        "Authorization": f"Bearer {token}",
        "Accept": "application/vnd.github+json"
    }

    asset_name = "unstable_release_metadata.json" if actor == "pandanotabear" else "release_metadata.json"

    url = f"https://api.github.com/repos/tursodatabase/turso-client-php/releases/{release_id}/assets"

    with open(file_path, "rb") as f:
        files = {"file": (asset_name, f, "application/json")}
        response = requests.post(url, files=files, headers=headers)

    if response.status_code in (200, 201):
        print(f"Release asset '{asset_name}' uploaded successfully")
    else:
        print(f"Failed to upload release asset: {response.status_code}")
        print(response.json())
        # Fail the job: a stale metadata channel is worse than a red run.
        sys.exit(1)


if __name__ == "__main__":
    # Parse command-line arguments
    parser = argparse.ArgumentParser(description="Upload the extension release metadata to a GitHub release.")
    parser.add_argument("--release-id", required=True, help="The ID of the GitHub release to attach the metadata asset to.")
    parser.add_argument("--actor", help="The GitHub actor (used to pick the metadata asset name).")
    parser.add_argument("--file-path", required=True, help="Path to the release metadata file to upload.")

    args = parser.parse_args()

    if args.release_id and args.actor and args.file_path:
        upload_release_asset(os.getenv("GITHUB_TOKEN"), args.release_id, args.actor, args.file_path)
    else:
        print("Please provide release-id, actor and file-path.")
        sys.exit(1)
