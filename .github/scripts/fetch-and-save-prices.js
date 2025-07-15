// .github/scripts/fetch-and-save-prices.js
const fs = require('fs');
const path = require('path');

const API_KEY = process.env.METALPRICE_API_KEY; // From GitHub Actions env
const API_URL = `https://api.metalpriceapi.com/v1/latest?api_key=${API_KEY}&base=PKR&symbols=XAU,XAG`;
const HISTORY_FILE_PATH = path.join(process.cwd(), 'prices-history.json');

async function fetchAndSaveDailyPrices() {
    console.log(`--- Starting daily price fetch ---`);

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
            console.log('Successfully updated prices-history.json. This will be committed by the workflow.');
        } else {
            console.log('Prices for today already recorded, no file update needed.');
        }

    } catch (error) {
        console.error('An error occurred during the daily price update:', error);
        process.exit(1);
    }
}

if (typeof fetch === 'undefined') {
    global.fetch = require('node-fetch');
}

fetchAndSaveDailyPrices();
