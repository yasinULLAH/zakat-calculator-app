// .github/scripts/fetch-and-save-prices.js
const fs = require('fs');
const path = require('path');
const axios = require('axios');
const cheerio = require('cheerio');

const SCRAPE_URL = 'https://www.forex.pk/bullion-rates.php';
const HISTORY_FILE_PATH = path.join(process.cwd(), 'prices-history.json');

async function fetchAndSaveDailyPrices() {
    console.log(`--- Starting daily price scrape ---`);
    console.log(`Scraping URL: ${SCRAPE_URL}`);

    try {
        const response = await axios.get(SCRAPE_URL, {
            headers: {
                'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
            }
        });
        const html = response.data;
        console.log('HTML fetched successfully. Parsing with Cheerio...');

        const $ = cheerio.load(html);

        let goldPrice = null; // Initialize as null to clearly distinguish from 0
        let silverPrice = null; // Initialize as null

        const mainTable = $('table').first(); // Target the first table on the page

        if (mainTable.length === 0) {
            throw new Error('Could not find any table on the page. Website structure might have changed.');
        }

        console.log('Iterating through table rows to find prices...');
        mainTable.find('tr').each((i, row) => {
            const columns = $(row).find('td');
            if (columns.length >= 3) { // Ensure there are at least 3 columns (Item, Buying, Selling)
                const itemName = columns.eq(0).text().trim(); // First column: Item name
                const buyingRateText = columns.eq(2).text().trim(); // Third column (index 2): Buying Rate

                // Log raw extracted text from columns
                console.log(`Row ${i}: Item: '${itemName}', Buying Rate Raw: '${buyingRateText}'`);

                // Check for Gold 24K 1 Tola
                if (itemName.toLowerCase().includes('gold') && itemName.toLowerCase().includes('24k') && itemName.toLowerCase().includes('1 tola')) {
                    goldPrice = parsePrice(buyingRateText);
                    console.log(`-> Identified Gold 24K 1 Tola. Parsed Price: ${goldPrice}`);
                }
                // Check for Silver 1 Tola
                else if (itemName.toLowerCase().includes('silver') && itemName.toLowerCase().includes('1 tola')) {
                    silverPrice = parsePrice(buyingRateText);
                    console.log(`-> Identified Silver 1 Tola. Parsed Price: ${silverPrice}`);
                }
            }
        });

        // Helper function to clean and parse price strings
        function parsePrice(priceString) {
            // Remove commas, currency symbols, and other non-numeric chars except dot
            let cleanedPrice = priceString.replace(/,/g, '').replace(/[^\d.]/g, '');
            if (cleanedPrice === '') {
                console.warn(`Attempted to parse empty string for price.`);
                return NaN;
            }
            return parseFloat(cleanedPrice);
        }

        // --- Final Validation after scraping ---
        if (goldPrice === null || isNaN(goldPrice) || goldPrice <= 0) {
            throw new Error(`Failed to scrape valid Gold price. Value: ${goldPrice}. Check selectors for gold.`);
        }
        if (silverPrice === null || isNaN(silverPrice) || silverPrice <= 0) {
            throw new Error(`Failed to scrape valid Silver price. Value: ${silverPrice}. Check selectors for silver.`);
        }
        // --- End Validation ---

        const dailyPrice = {
            date: new Date().toISOString().slice(0, 10), // YYYY-MM-DD
            gold: goldPrice,
            silver: silverPrice,
            unit: "PKR per Tola",
            timestamp: new Date().toLocaleString("en-PK", { timeZone: "Asia/Karachi" })
        };

        console.log('Scraped and Processed daily price:', dailyPrice);

        // Read existing history, append, and write back
        let history = [];
        if (fs.existsSync(HISTORY_FILE_PATH)) {
            const fileContent = fs.readFileSync(HISTORY_FILE_PATH, 'utf8');
            if (fileContent.trim()) {
                try {
                    history = JSON.parse(fileContent);
                } catch (parseError) {
                    console.warn('Could not parse existing history file, starting fresh:', parseError.message);
                    history = [];
                }
            }
        }

        const lastEntryDate = history.length > 0 ? history[history.length - 1].date : null;
        if (lastEntryDate !== dailyPrice.date) {
            history.push(dailyPrice);
            fs.writeFileSync(HISTORY_FILE_PATH, JSON.stringify(history, null, 2), 'utf8');
            console.log('Successfully updated prices-history.json locally.');
            console.log('::set-output name=prices_updated::true');
        } else {
            console.log('Prices for today already recorded, no file update needed.');
            console.log('::set-output name=prices_updated::false');
        }

    } catch (error) {
        console.error('An error occurred during the web scrape:', error.message);
        console.error('Stack trace:', error.stack); // More detailed error info
        process.exit(1);
    }
}

fetchAndSaveDailyPrices();
