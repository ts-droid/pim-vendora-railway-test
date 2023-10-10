# Cron Schedule

Command | Interval       | Description
------- |----------------| -----------
visma:fetch | Daily at 02:00 | Fetches updated data from Visma.net.
wgr:fetch | Daily at 05:00 | Fetches updated article data from WGR.
articles:calculate-sales-volume | Daily at 06:00 | Calculate the sales volume for each article.
customers:calculate-sales | Daily at 08:00 | Calculate historic sales for each customer
meta-data:generate-articles | Every five minutes | Generate meta data for articles missing meta data.
translateDatabase() | Every five minutes | Translates empty language columns in the database.
markSuppliers() | Daily | Marks customers as suppliers if they have bought any articles.
