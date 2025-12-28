import sys
import argparse
import requests
from dotenv import load_dotenv
import os

load_dotenv()

def update_store(storage_id, file_content):
    # Set up headers for the API request
    headers = {
        "Accept": "application/json",
        "User-Agent": "GitHub Actions",
    }

    # Prepare the update data
    update_data = {
        "data": file_content,
        "source": "GitHub Actions"
    }

    # Make the PATCH request to update the Store
    base_url = "https://json-storage-six.vercel.app"

    # For local testing, uncomment the following line and comment the above line
    # base_url = "http://localhost:4500"
    
    response = requests.put(f"{base_url}/{storage_id}", json=update_data, headers=headers)

    if response.status_code == 200:
        print(f"Store ID '{storage_id}' updated successfully")
    else:
        print(f"Failed to update Store ID '{storage_id}': {response.status_code}")
        print(response.json())

if __name__ == "__main__":
    # Parse command-line arguments
    parser = argparse.ArgumentParser(description="Update or retrieve a Store.")
    parser.add_argument("--storage-id", required=True, help="The ID of the Store.")
    parser.add_argument("--file-content", help="The content to update the Store file with (required for update).")

    args = parser.parse_args()

    # Update the Store if the actor and file content are provided
    if args.storage_id and args.file_content:
        update_store(args.storage_id, args.file_content)
    else:
        print("Please provide the storage ID and file content to update the Store.")
        sys.exit(1)
