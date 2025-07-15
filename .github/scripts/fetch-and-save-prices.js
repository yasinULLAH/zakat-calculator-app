// .github/scripts/fetch-and-save-prices.js
const fs = require('fs');
const path = require('path');

// GitHub Actions provides input via environment variables
const API_KEY = process.env.METALPRICE_API_KEY;
const GITHUB_TOKEN = process.env.GITHUB_TOKEN; // Automatically provided by GitHub Actions
const GITHUB_REPOSITORY = process.env.GITHUB_REPOSITORY; // e.g., 'your-username/your-repo-name'
const GITHUB_REF = process.env.GITHUB_REF; // e.g., 'refs/heads/main' or 'refs/heads/master'
const BRANCH_NAME = GITHUB_REF.split('/').pop(); // Extracts 'main' or 'master'

const API_URL = `https://api.metalpriceapi.com/v1/latest?api_key=${API_KEY}&base=PKR&symbols=XAU,XAG`;
const HISTORY_FILE_PATH = path.join(process.cwd(), 'prices-history.json'); // Current working directory is the repo root

async function fetchAndSaveDailyPrices() {
    console.log(`--- Starting daily price fetch for branch: ${BRANCH_NAME} ---`);

    if (!API_KEY) {
        console.error('METALPRICE_API_KEY is not set in GitHub Secrets!');
        process.exit(1);
    }

    try {
        // 1. Fetch data from MetalpriceAPI
        const response = await fetch(API_URL);
        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(`API fetch failed with status ${response.status}: ${errorText}`);
        }
        const apiData = await response.json();

        if (!apiData || !apiData.rates || !apiData.rates.XAU || !apiData.rates.XAG) {
            throw new Error('API response missing expected gold/silver data.');
        }

        // Convert per ounce to per Tola (adjust if your target website uses different units)
        // 1 Tola = 11.6638 grams
        // 1 Troy Ounce = 31.1035 grams
        const gramsPerTola = 11.6638;
        const gramsPerOunce = 31.1035;
        const tolaFactor = gramsPerTola / gramsPerOunce;

        const dailyPrice = {
            date: new Date().toISOString().slice(0, 10), // YYYY-MM-DD
            gold: parseFloat((apiData.rates.XAU * tolaFactor).toFixed(2)),
            silver: parseFloat((apiData.rates.XAG * tolaFactor).toFixed(2)),
            unit: "PKR per Tola",
            timestamp: new Date().toLocaleString("en-PK", { timeZone: "Asia/Karachi" })
        };

        console.log('Fetched daily price:', dailyPrice);

        // 2. Read existing history
        let history = [];
        if (fs.existsSync(HISTORY_FILE_PATH)) {
            const fileContent = fs.readFileSync(HISTORY_FILE_PATH, 'utf8');
            try {
                history = JSON.parse(fileContent);
            } catch (parseError) {
                console.warn('Could not parse existing history file, starting fresh:', parseError);
                history = [];
            }
        }

        // 3. Append new data (only if not already added for today)
        const lastEntryDate = history.length > 0 ? history[history.length - 1].date : null;
        if (lastEntryDate !== dailyPrice.date) {
            history.push(dailyPrice);
            fs.writeFileSync(HISTORY_FILE_PATH, JSON.stringify(history, null, 2), 'utf8');
            console.log('Successfully updated prices-history.json.');
        } else {
            console.log('Prices for today already recorded, no update needed.');
            return; // Exit early if no change
        }

        // 4. Commit changes back to the repository using Git commands
        // This requires the workflow to checkout the repository with write permissions
        // GitHub Actions automatically sets up Git for you.
        console.log('Committing changes...');
        const { execSync } = require('child_process');

        execSync(`git config user.name "github-actions[bot]"`, { stdio: 'inherit' });
        execSync(`git config user.email "github-actions[bot]@users.noreply.github.com"`, { stdio: 'inherit' });
        execSync(`git add ${HISTORY_FILE_PATH}`, { stdio: 'inherit' });
        execSync(`git commit -m "Automated: Update daily gold/silver prices for ${dailyPrice.date}"`, { stdio: 'inherit' });
        execSync(`git push origin ${BRANCH_NAME}`, { stdio: 'inherit' });

        console.log('Changes committed and pushed successfully!');

    } catch (error) {
        console.error('An error occurred during the daily price update:', error);
        process.exit(1); // Exit with an error code to mark the workflow as failed
    }
}

// Node.js doesn't have native 'fetch' until Node 18+.
// For GitHub Actions (often Node 16 or 18 by default), using a polyfill or direct import is safer.
// We'll use a simple polyfill for fetch here to ensure compatibility.
// If your workflow uses Node.js 18+ explicitly, you can remove this.
if (typeof fetch === 'undefined') {
    global.fetch = require('node-fetch');
}

fetchAndSaveDailyPrices();
