// .github/scripts/fetch-and-save-prices.js
const fs = require('fs');
const path = require('path');
const axios = require('axios');
const cheerio = require('cheerio');

const SCRAPE_URL = 'https://www.forex.pk/bullion-rates.php'; // The website to scrape
const HISTORY_FILE_PATH = path.join(process.cwd(), 'prices-history.json'); // Path to your data file

async function fetchAndSaveDailyPrices() {
    console.log(`--- Starting daily price scrape ---`);
    console.log(`Scraping URL: ${SCRAPE_URL}`);

    try {
        // 1. Fetch the HTML content
        const response = await axios.get(SCRAPE_URL);
        const html = response.data;
        console.log('HTML fetched successfully. Parsing with Cheerio...');

        // 2. Load HTML into Cheerio
        const $ = cheerio.load(html);

        let goldPrice = 0;
        let silverPrice = 0;

        // Targeting the first table on the page, then iterating through its rows
        const mainTable = $('table').first(); // Get the first table element

        if (mainTable.length === 0) {
            throw new Error('Could not find any table on the page. Website structure might have changed.');
        }

        // Iterate over each table row (tr)
        mainTable.find('tr').each((i, row) => {
            const columns = $(row).find('td'); // Get all columns (td) in the current row
            if (columns.length > 0) {
                const itemName = columns.eq(0).text().trim().toLowerCase(); // First column text (e.g., "Gold 24K 1 Tola")
                const buyingRateText = columns.eq(2).text().trim(); // Third column text (e.g., "230,500")

                // Look for Gold 24K 1 Tola
                if (itemName.includes('gold') && itemName.includes('24k') && itemName.includes('1 tola')) {
                    goldPrice = parsePrice(buyingRateText);
                    console.log(`Found Gold Price Text: '${buyingRateText}', Parsed: ${goldPrice}`);
                }
                // Look for Silver 1 Tola
                else if (itemName.includes('silver') && itemName.includes('1 tola')) {
                    silverPrice = parsePrice(buyingRateText);
                    console.log(`Found Silver Price Text: '${buyingRateText}', Parsed: ${silverPrice}`);
                }
            }
        });

        // Helper function to clean and parse price strings
        function parsePrice(priceString) {
            // Remove commas and ensure only numbers and dots remain
            let cleanedPrice = priceString.replace(/,/g, '').replace(/[^\d.]/g, '');
            return parseFloat(cleanedPrice);
        }

        // Validate parsed prices
        if (isNaN(goldPrice) || goldPrice <= 0) {
            console.warn(`Gold price invalid or zero: ${goldPrice}. Scraped text was: ${goldPriceText}`);
            throw new Error('Failed to scrape valid Gold price.');
        }
        if (isNaN(silverPrice) || silverPrice <= 0) {
             console.warn(`Silver price invalid or zero: ${silverPrice}. Scraped text was: ${silverPriceText}`);
             throw new Error('Failed to scrape valid Silver price.');
        }

        const dailyPrice = {
            date: new Date().toISOString().slice(0, 10), // YYYY-MM-DD
            gold: goldPrice,
            silver: silverPrice,
            unit: "PKR per Tola",
            timestamp: new Date().toLocaleString("en-PK", { timeZone: "Asia/Karachi" })
        };

        console.log('Scraped and Processed daily price:', dailyPrice);

        // 3. Read existing history
        let history = [];
        if (fs.existsSync(HISTORY_FILE_PATH)) {
            const fileContent = fs.readFileSync(HISTORY_FILE_PATH, 'utf8');
            if (fileContent.trim()) {
                try {
                    history = JSON.parse(fileContent);
                } catch (parseError) {
                    console.warn('Could not parse existing history file, starting fresh:', parseError);
                    history = [];
                }
            }
        }

        // 4. Append new data (only if not already added for today)
        const lastEntryDate = history.length > 0 ? history[history.length - 1].date : null;
        if (lastEntryDate !== dailyPrice.date) {
            history.push(dailyPrice);
            fs.writeFileSync(HISTORY_FILE_PATH, JSON.stringify(history, null, 2), 'utf8');
            console.log('Successfully updated prices-history.json locally.');
            console.log('::set-output name=prices_updated::true'); // Signal to workflow
        } else {
            console.log('Prices for today already recorded, no file update needed.');
            console.log('::set-output name=prices_updated::false'); // Signal to workflow
        }

    } catch (error) {
        console.error('An error occurred during the web scrape:', error.message);
        console.error(error.stack); // Log full stack trace for debugging
        process.exit(1); // Exit with error code
    }
}

fetchAndSaveDailyPrices();
