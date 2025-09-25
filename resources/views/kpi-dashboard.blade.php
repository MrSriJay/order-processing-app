 <!DOCTYPE html>
       <html lang="en">
       <head>
           <meta charset="UTF-8">
           <meta name="viewport" content="width=device-width, initial-scale=1.0">
           <title>KPI Dashboard - Order Processing App</title>
           <script src="https://cdn.tailwindcss.com"></script>
           <style>
               body {
                   background-color: #f3f4f6;
               }
               .kpi-card {
                   transition: transform 0.3s ease-in-out;
               }
               .kpi-card:hover {
                   transform: scale(1.05);
               }
               #error-message {
                   display: none;
               }
           </style>
       </head>
       <body class="min-h-screen flex flex-col items-center justify-center p-4">
           <div class="w-full max-w-4xl">
               <h1 class="text-3xl font-bold text-gray-800 mb-6 text-center">Order Processing KPI Dashboard</h1>

               <!-- Error Message -->
               <div id="error-message" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"></div>

               <!-- KPI Cards -->
               <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
                   <div class="kpi-card bg-white rounded-lg shadow-lg p-6 text-center">
                       <h2 class="text-xl font-semibold text-gray-700">Total Revenue</h2>
                       <p id="total-revenue" class="text-2xl font-bold text-blue-600">LKR 0.00</p>
                   </div>
                   <div class="kpi-card bg-white rounded-lg shadow-lg p-6 text-center">
                       <h2 class="text-xl font-semibold text-gray-700">Total Orders</h2>
                       <p id="order-count" class="text-2xl font-bold text-blue-600">0</p>
                   </div>
                   <div class="kpi-card bg-white rounded-lg shadow-lg p-6 text-center">
                       <h2 class="text-xl font-semibold text-gray-700">Average Order Value</h2>
                       <p id="average-order-value" class="text-2xl font-bold text-red-600">LKR 0.00</p>
                   </div>
               </div>

               <!-- Leaderboard Table -->
               <div class="bg-white rounded-lg shadow-lg p-6">
                   <h2 class="text-xl font-semibold text-gray-700 mb-4">Top Customers Leaderboard</h2>
                   <table class="w-full text-left">
                       <thead>
                           <tr class="border-b">
                               <th class="py-2 px-4 text-gray-600">Rank</th>
                               <th class="py-2 px-4 text-gray-600">Name</th>
                               <th class="py-2 px-4 text-gray-600">Email</th>
                               <th class="py-2 px-4 text-gray-600">Total Spent</th>
                           </tr>
                       </thead>
                       <tbody id="leaderboard-table" class="text-gray-700">
                           <tr>
                               <td colspan="4" class="py-4 text-center">Loading...</td>
                           </tr>
                       </tbody>
                   </table>
               </div>
           </div>

           <script>
               async function fetchKpiData() {
                   const errorMessage = document.getElementById('error-message');
                   try {
                       const response = await fetch('/kpis');
                       if (!response.ok) {
                           throw new Error(`HTTP error! Status: ${response.status}`);
                       }
                       const data = await response.json();
                       console.log('API Response:', data); 

                       // Update KPI cards
                       document.getElementById('total-revenue').textContent = `LKR ${data.kpis.total_revenue}`;
                       document.getElementById('order-count').textContent = data.kpis.order_count;
                       document.getElementById('average-order-value').textContent = `LKR ${data.kpis.average_order_value}`;

                       // Update leaderboard table
                       const leaderboardTable = document.getElementById('leaderboard-table');
                       leaderboardTable.innerHTML = '';
                       if (data.leaderboard.length === 0) {
                           leaderboardTable.innerHTML = `
                               <tr><td colspan="4" class="py-4 text-center">No leaderboard data available</td></tr>
                           `;
                       } else {
                           data.leaderboard.forEach((customer, index) => {
                               const row = document.createElement('tr');
                               row.className = 'border-b';
                               row.innerHTML = `
                                   <td class="py-2 px-4">${index + 1}</td>
                                   <td class="py-2 px-4">${customer.name || 'Unknown'}</td>
                                   <td class="py-2 px-4">${customer.email || 'N/A'}</td>
                                   <td class="py-2 px-4">LKR ${customer.total}</td>
                               `;
                               leaderboardTable.appendChild(row);
                           });
                       }
                   } catch (error) {
                       console.error('Error fetching KPI data:', error);
                       errorMessage.textContent = `Error loading data: ${error.message}`;
                       errorMessage.style.display = 'block';
                       document.getElementById('leaderboard-table').innerHTML = `
                           <tr><td colspan="4" class="py-4 text-center text-red-600">Error loading data</td></tr>
                       `;
                   }
               }

               window.onload = fetchKpiData;
           </script>
       </body>
       </html>