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
        // Uncomment to see partial HTML in logs for debugging, but keep it commented for normal runs
        // console.log('--- HTML Content Snippet (first 2000 chars) ---');
        // console.log(html.substring(0, 2000));
        // console.log('------------------------------------');

        const $ = cheerio.load(html);

        let goldPrice = null;
        let silverPrice = null;

        // --- THE MOST CRITICAL CHANGE: Target the correct table precisely ---
        // Find the <h2> tag with the specific text "Bullion / Gold Price Today"
        // Then get its next sibling, which should be the correct table.
        const bullionTable = $('h2:contains("Bullion / Gold Price Today")').next('table');

        if (bullionTable.length === 0) {
            throw new Error('Could not find the "Bullion / Gold Price Today" table. Website structure might have changed.');
        }

        console.log('Found bullion table. Iterating through rows...');
        bullionTable.find('tr').each((i, row) => {
            const columns = $(row).find('td');
            // Ensure there are at least 4 columns (Metal, Symbol, 10 Gm, 1 Tola)
            if (columns.length >= 4) { // Increased to 4 because we're looking at 1 Tola (4th column in header row if 1-indexed)
                const metalName = columns.eq(0).text().trim(); // e.g., "Gold", "Silver"
                const tolaPriceText = columns.eq(3).text().trim(); // This is the "PKR for 1 Tola" column (index 3)

                // Log raw extracted text for debugging
                console.log(`Row ${i}: Metal Name: '${metalName}', 1 Tola Price Raw: '${tolaPriceText}' (from col 3)`);

                // Identify Gold
                if (metalName.toLowerCase() === 'gold') {
                    goldPrice = parsePrice(tolaPriceText);
                    console.log(`-> Identified Gold. Parsed Price: ${goldPrice}`);
                }
                // Identify Silver
                else if (metalName.toLowerCase() === 'silver') {
                    silverPrice = parsePrice(tolaPriceText);
                    console.log(`-> Identified Silver. Parsed Price: ${silverPrice}`);
                }
            }
        });

        // Helper function to clean and parse price strings
        function parsePrice(priceString) {
            // Remove commas and any non-digit/non-dot characters.
            let cleanedPrice = priceString.replace(/,/g, '').replace(/[^\d.]/g, '');
            if (cleanedPrice === '') {
                console.warn(`Attempted to parse empty string for price.`);
                return NaN;
            }
            return parseFloat(cleanedPrice);
        }

        // --- Final Validation after scraping ---
        if (goldPrice === null || isNaN(goldPrice) || goldPrice <= 0) {
            throw new Error(`Failed to scrape valid Gold price. Value: ${goldPrice}. Ensure 'Gold' row is found and its 1 Tola price is valid.`);
        }
        if (silverPrice === null || isNaN(silverPrice) || silverPrice <= 0) {
            throw new Error(`Failed to scrape valid Silver price. Value: ${silverPrice}. Ensure 'Silver' row is found and its 1 Tola price is valid.`);
        }
        // --- End Validation ---

        const dailyPrice = {
            date: new Date().toISOString().slice(0, 10), // YYYY-MM-DD
            gold: goldPrice,
            silver: silverPrice,
            unit: "PKR per Tola", // Confirmed from the table's header
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
        console.error('Stack trace:', error.stack);
        process.exit(1);
    }
}

fetchAndSaveDailyPrices();
