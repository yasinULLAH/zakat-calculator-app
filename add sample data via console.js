async function addSampleData() {
    if (!window.db || !window.STORES || !window.dbRequest) {
        console.error("❌ App not fully initialized. Please ensure the page is loaded and try again.");
        return;
    }

    console.group("Generating Zakat Manager Sample Data");

    const getRandomInt = (min, max) => Math.floor(Math.random() * (max - min + 1)) + min;
    const getRandomDate = (start, end) => new Date(start.getTime() + Math.random() * (end.getTime() - start.getTime()));

    try {
        console.log("🔥 Clearing existing data...");
        const clearTx = db.transaction(Object.values(STORES), 'readwrite');
        await Promise.all([
            dbRequest(clearTx.objectStore(STORES.settings).clear()),
            dbRequest(clearTx.objectStore(STORES.calculations).clear()),
            dbRequest(clearTx.objectStore(STORES.payments).clear())
        ]);
        console.log("🧹 Data cleared.");

        const tx = db.transaction(Object.values(STORES), 'readwrite');
        const currentYear = new Date().getFullYear();
        const promises = [];

        // 1. Generate Settings
        const settingsData = {
            id: 'current',
            goldPrice: 240000,
            silverPrice: 2800,
            currencySymbol: 'PKR',
            zakatAnniversary: `${currentYear}-05-20`
        };
        promises.push(dbRequest(tx.objectStore(STORES.settings).put(settingsData)));
        console.log("⚙️  1 Settings entry created.");

        // 2. Generate Calculations
        const sampleCalculations = [];
        for (let i = 0; i < 10; i++) {
            const year = currentYear - i;
            const cash = getRandomInt(50000, 700000);
            const businessGoods = getRandomInt(0, 1000000);
            const receivables = getRandomInt(0, 150000);
            const liabilities = getRandomInt(0, 50000);
            const zakatableAssets = cash + businessGoods + receivables - liabilities;

            const calc = {
                year: year,
                goldKarat: 22,
                gold: getRandomInt(5, 25),
                silver: getRandomInt(10, 50),
                cash: cash,
                businessGoods: businessGoods,
                receivables: receivables,
                liabilities: liabilities,
                goldPrice: settingsData.goldPrice,
                silverPrice: settingsData.silverPrice,
                nisabValue: 52.5 * settingsData.silverPrice,
                totalAssets: zakatableAssets + liabilities, // Simplified for sample data
                zakatableAssets: zakatableAssets,
                isZakatDue: true,
                zakatAmount: zakatableAssets * 0.025,
                calculationDate: new Date(year, 5, 20).toISOString()
            };
            sampleCalculations.push(calc);
            promises.push(dbRequest(tx.objectStore(STORES.calculations).put(calc)));
        }
        console.log("🧮 10 Calculation entries created for years:", sampleCalculations.map(c => c.year).join(', '));

        // 3. Generate Payments
        const recipients = ["Edhi Foundation", "Shaukat Khanum", "Akhuwat", "Local Madrasa", "Relative (Ahmad)", "Neighbor (Fatima)", "Indus Hospital", "Saylani Trust"];
        const categories = ["Orphans", "Medical Aid", "Education", "Food", "Relative Support", "Institution"];

        for (let i = 0; i < 10; i++) {
            const randomCalc = sampleCalculations[getRandomInt(0, 9)];
            const payment = {
                year: randomCalc.year,
                amount: getRandomInt(randomCalc.zakatAmount * 0.1, randomCalc.zakatAmount * 0.5),
                date: getRandomDate(new Date(randomCalc.year, 5, 21), new Date(randomCalc.year, 11, 31)).toISOString().slice(0, 10),
                recipient: recipients[getRandomInt(0, recipients.length - 1)],
                category: categories[getRandomInt(0, categories.length - 1)],
                notes: "Sample payment entry."
            };
            promises.push(dbRequest(tx.objectStore(STORES.payments).put(payment)));
        }
        console.log("💸 10 Payment entries created.");

        await Promise.all(promises);
        console.log("✅ Sample data generated successfully!");
        console.info("➡️ PLEASE RELOAD THE PAGE to see the new data.");

    } catch (error) {
        console.error("❌ An error occurred while generating sample data:", error);
    } finally {
        console.groupEnd();
    }
}

addSampleData();