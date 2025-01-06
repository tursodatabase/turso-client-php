import os
import sys
import argparse
import requests

def update_gist(gist_id, actor, file_content):
    # Retrieve the GIST_TOKEN from the environment
    token = os.getenv("GIST_TOKEN")
    if not token:
        print("GIST_TOKEN environment variable is not set.")
        sys.exit(1)

    # Set up headers for the API request
    headers = {
        "Authorization": f"Bearer {token}",
        "Accept": "application/vnd.github+json"
    }

    # Determine the file name to update based on the GitHub actor
    file_name = "unstable_release_metadata.json" if actor == "pandanotabear" else "release_metadata.json"

    # Prepare the update data
    update_data = {
        "files": {
            file_name: {
                "content": file_content
            }
        }
    }

    # Make the PATCH request to update the Gist
    response = requests.patch(f"https://api.github.com/gists/{gist_id}", json=update_data, headers=headers)

    if response.status_code == 200:
        print(f"Gist '{file_name}' updated successfully")
    else:
        print(f"Failed to update gist: {response.status_code}")
        print(response.json())

if __name__ == "__main__":
    # Parse command-line arguments
    parser = argparse.ArgumentParser(description="Update or retrieve a Gist.")
    parser.add_argument("--gist-id", required=True, help="The ID of the Gist.")
    parser.add_argument("--actor", help="The GitHub actor (required for update).")
    parser.add_argument("--file-content", help="The content to update the Gist file with (required for update).")

    args = parser.parse_args()

    # Update the Gist if the actor and file content are provided
    if args.actor and args.file_content:
        update_gist(args.gist_id, args.actor, args.file_content)
    else:
        print("Please provide the actor and file content to update the Gist.")
        sys.exit(1)