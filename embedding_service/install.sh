#!/bin/bash
echo "Setting up local embedding service..."

# Create virtual environment
python3 -m venv venv
source venv/bin/activate

# Install requirements
pip install --upgrade pip
pip install -r requirements.txt

echo "Setup complete!"
echo "To start the service, run: ./start.sh"