// Live Dashboard JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Get event ID from URL
    const urlParams = new URLSearchParams(window.location.search);
    const eventId = urlParams.get('event_id');
    
    // Countdown for auto refresh
    let countdown = 15;
    const countdownEl = document.getElementById('countdown');
    let countdownInterval;

    function startCountdown() {
        if (countdownInterval) clearInterval(countdownInterval);
        countdown = 15;
        if (countdownEl) countdownEl.textContent = countdown;
        countdownInterval = setInterval(() => {
            countdown--;
            if (countdownEl) {
                countdownEl.textContent = countdown;
            }
            if (countdown <= 0) {
                clearInterval(countdownInterval);
                fetchLiveData();
                startCountdown();
            }
        }, 1000);
    }

    // Botão Atualizar Agora
    const btnAtualizar = document.querySelector('button.btn-outline-primary, .btn-outline-primary');
    if (btnAtualizar) {
        btnAtualizar.onclick = null;
        btnAtualizar.addEventListener('click', () => location.reload());
    }

    startCountdown();
    
    // Function to fetch live data
    function fetchLiveData() {
        if (!eventId) return;
        
        fetch(`live_data.php?event_id=${eventId}`)
            .then(response => response.json())
            .then(data => {
                updateDashboardData(data);
            })
            .catch(error => {
                console.error('Error fetching live data:', error);
            });
    }
    
    // Function to fetch event statistics
    function fetchEventStats() {
        if (!eventId) return;
        
        fetch(`event_stats.php?event_id=${eventId}`)
            .then(response => response.json())
            .then(data => {
                updateEventStats(data);
            })
            .catch(error => {
                console.error('Error fetching event stats:', error);
            });
    }
    
    // Function to update event statistics
    function updateEventStats(data) {        // Update capacity information with breakdown for invite vs paid tickets
        if (data.capacity) {
            const capacityProgress = document.getElementById('capacity-progress');
            const capacitySold = document.getElementById('capacity-sold');
            const capacityTotal = document.getElementById('capacity-total');
            
            if (capacityProgress) {
                capacityProgress.style.width = data.capacity.percentage + '%';
                capacityProgress.textContent = data.capacity.percentage + '%';
                capacityProgress.setAttribute('aria-valuenow', data.capacity.percentage);
            }
            
            if (capacitySold) {
                capacitySold.textContent = data.capacity.sold.toLocaleString('pt-PT');
            }
            
            if (capacityTotal) {
                capacityTotal.textContent = data.capacity.total.toLocaleString('pt-PT');
            }
            
            // Add invite vs paid capacity breakdown if available
            if (data.capacity.invite_capacity !== undefined) {
                const capacityBreakdownEl = document.getElementById('capacity-breakdown');
                if (capacityBreakdownEl) {
                    capacityBreakdownEl.innerHTML = `
                        <div class="mt-2 small">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Bilhetes pagos:</span>
                                <span><strong>${data.capacity.paid_sold.toLocaleString('pt-PT')}</strong> / ${data.capacity.paid_capacity.toLocaleString('pt-PT')}</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Convites:</span>
                                <span><strong>${data.capacity.invite_sold.toLocaleString('pt-PT')}</strong> / ${data.capacity.invite_capacity.toLocaleString('pt-PT')}</span>
                            </div>
                        </div>
                    `;
                }
            }
        }
          // Update sales chart with enhanced data
        if (data.salesChart && window.salesChart) {
            window.salesChart.data.labels = data.salesChart.dates;
            window.salesChart.data.datasets[0].data = data.salesChart.counts;
            window.salesChart.data.datasets[1].data = data.salesChart.revenue;
            
            // Add online and POS data if available
            if (data.salesChart.online_counts && data.salesChart.pos_counts) {
                if (window.salesChart.data.datasets.length < 4) {
                    // Add new datasets for online and POS if they don't exist
                    window.salesChart.data.datasets.push({
                        label: 'Vendas Online',
                        data: data.salesChart.online_counts,
                        borderColor: 'rgba(54, 162, 235, 1)',
                        backgroundColor: 'rgba(0, 0, 0, 0)',
                        borderDash: [5, 5],
                        borderWidth: 2,
                        yAxisID: 'y',
                        tension: 0.4
                    });
                    
                    window.salesChart.data.datasets.push({
                        label: 'Vendas Locais',
                        data: data.salesChart.pos_counts,
                        borderColor: 'rgba(255, 159, 64, 1)',
                        backgroundColor: 'rgba(0, 0, 0, 0)',
                        borderDash: [5, 5],
                        borderWidth: 2,
                        yAxisID: 'y',
                        tension: 0.4
                    });
                } else {
                    // Update existing datasets
                    window.salesChart.data.datasets[2].data = data.salesChart.online_counts;
                    window.salesChart.data.datasets[3].data = data.salesChart.pos_counts;
                }
            }
            
            window.salesChart.update();
        }else if (data.salesChart && document.getElementById('salesChart')) {
            const ctx = document.getElementById('salesChart').getContext('2d');            window.salesChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.salesChart.dates,
                    datasets: [
                        {
                            label: 'Total Bilhetes',
                            data: data.salesChart.counts,
                            borderColor: 'rgba(75, 192, 192, 1)',
                            backgroundColor: 'rgba(75, 192, 192, 0.2)',
                            yAxisID: 'y',
                            tension: 0.4
                        },
                        {
                            label: 'Receita (€)',
                            data: data.salesChart.revenue,
                            borderColor: 'rgba(255, 99, 132, 1)',
                            backgroundColor: 'rgba(255, 99, 132, 0.2)',
                            yAxisID: 'y1',
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Bilhetes'
                            },
                            ticks: {
                                precision: 0
                            }
                        },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            grid: {
                                drawOnChartArea: false
                            },
                            title: {
                                display: true,
                                text: 'Receita (€)'
                            }
                        }
                    }
                }
            });
        }
        
        // Update ticket types container
        if (data.ticketTypes && data.ticketTypes.length > 0) {
            const container = document.getElementById('ticket-types-container');
            if (container) {
                let html = '';
                
                data.ticketTypes.forEach(type => {
                    html += `
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>${escapeHtml(type.name)}</span>
                                <span><strong>${type.count}</strong>/<small>${type.capacity}</small></span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar ${getProgressBarClass(type.percentage)}" 
                                     role="progressbar" 
                                     style="width: ${type.percentage}%" 
                                     aria-valuenow="${type.percentage}" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100">${type.percentage}%</div>
                            </div>
                        </div>
                    `;
                });
                
                container.innerHTML = html;
            }
        }
    }
    
    // Helper function to get progress bar class based on percentage
    function getProgressBarClass(percentage) {
        if (percentage < 33) return 'bg-info';
        if (percentage < 66) return 'bg-warning';
        if (percentage < 90) return 'bg-success';
        return 'bg-danger';
    }
    
    // Number animation for stats
    const animateNumbers = () => {
        const numberElements = document.querySelectorAll('.stats-card .number');
        numberElements.forEach(element => {
            // IGNORAR O TOTAL DE BILHETES ESTÁTICO
            if (element.id === 'totalTicketsStatic') return;
            const target = parseFloat(element.getAttribute('data-value'));
            const isCurrency = element.getAttribute('data-currency') === 'true';
            const isPercentage = element.getAttribute('data-percentage') === 'true';
            
            let startValue = 0;
            const duration = 1000;
            const startTime = performance.now();
            
            const updateNumber = (currentTime) => {
                const elapsedTime = currentTime - startTime;
                const progress = Math.min(elapsedTime / duration, 1);
                const easedProgress = easeOutQuad(progress);
                const currentValue = startValue + (target - startValue) * easedProgress;
                
                if (isCurrency) {
                    element.textContent = currentValue.toLocaleString('pt-PT', { 
                        minimumFractionDigits: 2, 
                        maximumFractionDigits: 2 
                    }) + '€';
                } else if (isPercentage) {
                    element.textContent = Math.round(currentValue) + '%';
                } else {
                    element.textContent = Math.round(currentValue).toLocaleString('pt-PT');
                }
                
                if (progress < 1) {
                    requestAnimationFrame(updateNumber);
                }
            };
            
            requestAnimationFrame(updateNumber);
        });
    };
    
    // Easing function
    const easeOutQuad = (x) => {
        return 1 - (1 - x) * (1 - x);
    };
      // Initialize animations
    animateNumbers();    // Function to update dashboard with new data and enhanced metrics
    function updateDashboardData(data) {
        // Update main statistics cards
        // updateStatCard('totalTickets', data.totalTickets); // REMOVIDO: valor total de bilhetes é estático, só PHP
        updateStatCard('totalCheckIns', data.totalCheckIns);
        updateStatCard('checkInPercentage', data.checkInPercentage, true);
        
        // Update revenue breakdown with detailed payment methods
        const enhancedRevenueByMethod = {
            Online: data.onlineRevenue,
            Onsite: data.posRevenue,
            Card: data.cardRevenue,
            Cash: data.cashRevenue
        };
        // updateRevenueBreakdown(enhancedRevenueByMethod); // REMOVIDO: badges de receita agora são fixos via PHP
        
        // Update check-in stats with breakdown (NÃO alterar HTML, só se usar via JS)
        // updateCheckInBreakdown(data.onlineCheckIns, data.localCheckIns, data.inviteCheckIns, data.onlineCheckInPercentage, data.localCheckInPercentage, data.inviteCheckInPercentage);
        
        // Update recent check-ins list with enhanced details
        updateRecentCheckIns(data.recentCheckIns);
        
    // Update hourly chart with only total check-ins
    if (window.hourlyCheckInsChart && data.hourlyChart) {
        window.hourlyCheckInsChart.data.datasets = [
            {
                label: 'Total Check-ins',
                data: data.hourlyChart.data,
                borderColor: 'rgba(75, 192, 192, 1)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.4,
                fill: true
            }
        ];
        window.hourlyCheckInsChart.update();
    }
    }
    
    // Function to update a stat card
    function updateStatCard(id, newValue, isPercentage = false, isCurrency = false) {
        // Só atualiza o card correto, nunca o total de bilhetes
        const element = document.getElementById(id);
        if (!element) return;
        if (element.id === 'totalTicketsStatic') return; // Nunca altera o total de bilhetes
        element.setAttribute('data-value', newValue);
        let displayValue = newValue;
        if (isCurrency) {
            displayValue = newValue.toLocaleString('pt-PT', { 
                minimumFractionDigits: 2, 
                maximumFractionDigits: 2 
            }) + '€';
        } else if (isPercentage) {
            displayValue = Math.round(newValue) + '%';
        } else {
            displayValue = Math.round(newValue).toLocaleString('pt-PT');
        }
        element.textContent = displayValue;
        element.classList.add('highlight');
        setTimeout(() => {
            element.classList.remove('highlight');
        }, 1000);
    }    // Function to update recent check-ins with enhanced details
    function updateRecentCheckIns(checkIns) {
        const container = document.querySelector('.check-ins-list');
        if (!container || !checkIns.length) return;
        
        let html = '';
        
        checkIns.forEach(checkIn => {
            // Ticket type badge
            let ticketTypeLabel = '';
            if (checkIn.type === 'invite') {
                ticketTypeLabel = '<span class="badge bg-secondary">Convite</span>';
            } else {
                ticketTypeLabel = '<span class="badge bg-primary">Pago</span>';
            }
            
            // Payment method badge
            let paymentMethodLabel = '';
            if (checkIn.payment_method) {
                const badgeClass = checkIn.payment_method === 'Card' ? 'bg-success' : 
                                  (checkIn.payment_method === 'Cash' ? 'bg-danger' : 'bg-info');
                paymentMethodLabel = `<span class="badge ${badgeClass}">${escapeHtml(checkIn.payment_method)}</span>`;
            }
            
            html += `
                <li class="list-group-item check-in-item">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${escapeHtml(checkIn.name)}</strong>
                            <div class="text-muted small">
                                ${checkIn.product_name ? `<span class="fw-semi-bold">${escapeHtml(checkIn.product_name)}</span> · ` : ''}
                                ${escapeHtml(checkIn.zone || 'Zona não especificada')}
                            </div>
                            <div class="mt-1">
                                ${ticketTypeLabel}
                                ${paymentMethodLabel}
                                ${checkIn.ticket ? `<span class=\"badge bg-light text-dark\">${escapeHtml(checkIn.ticket)}</span>` : ''}
                            </div>
                        </div>
                        <div class="check-in-time">
                            ${checkIn.formatted_time}
                        </div>
                    </div>
                </li>
            `;
        });
        
        if (html) {
            container.innerHTML = html;
        }
    }
    
    // Helper function to escape HTML
    function escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
});

// Initialize all charts with proper responsive settings
function initializeCharts(hourlyData, ticketTypesData, zoneData, extrasData) {
    // Hourly Check-ins Chart com apenas total
    if (document.getElementById('hourlyCheckInsChart')) {
        const hourlyCtx = document.getElementById('hourlyCheckInsChart').getContext('2d');
        window.hourlyCheckInsChart = new Chart(hourlyCtx, {
            type: 'line',
            data: {
                labels: hourlyData.labels,
                datasets: [
                    {
                        label: 'Total Check-ins',
                        data: hourlyData.data,
                        borderColor: 'rgba(75, 192, 192, 1)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        tension: 0.4,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        enabled: true,
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.raw;
                            }
                        }
                    }
                }
            }
        });
    }
      // Ticket Types Chart with enhanced visualization
    if (document.getElementById('ticketTypesChart')) {
        const typesCtx = document.getElementById('ticketTypesChart').getContext('2d');
        
        // Get ticket types and their corresponding types (paid vs invite)
        const labels = ticketTypesData.labels || [];
        const data = ticketTypesData.data || [];
        const types = ticketTypesData.types || [];
        
        // Generate dynamic colors based on ticket type
        const backgroundColor = types.map(type => {
            return type === 'invite' ? 'rgba(153, 102, 255, 0.7)' : 'rgba(54, 162, 235, 0.7)';
        });
        
        const borderColor = types.map(type => {
            return type === 'invite' ? 'rgba(153, 102, 255, 1)' : 'rgba(54, 162, 235, 1)';
        });
        
        window.ticketTypesChart = new Chart(typesCtx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: backgroundColor,
                    borderColor: borderColor,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'right'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw;
                                const total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return label + ': ' + value + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Zone Check-ins Chart
    if (document.getElementById('zoneCheckInsChart')) {
        const zonesCtx = document.getElementById('zoneCheckInsChart').getContext('2d');
        window.zoneCheckInsChart = new Chart(zonesCtx, {
            type: 'bar',
            data: {
                labels: zoneData.labels,
                datasets: [{
                    label: 'Check-ins',
                    data: zoneData.data,
                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                }
            }
        });
    }
    
    // Extras Usage Chart
    if (document.getElementById('extrasUsageChart')) {
        const extrasCtx = document.getElementById('extrasUsageChart').getContext('2d');
        window.extrasUsageChart = new Chart(extrasCtx, {
            type: 'bar',
            data: {
                labels: extrasData.labels,
                datasets: [{
                    label: 'Quantidade',
                    data: extrasData.data,
                    backgroundColor: 'rgba(255, 99, 132, 0.7)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                }
            }
        });
    }
}

// Proteção: nunca alterar o breakdown de bilhetes via JS!
    // O breakdown de bilhetes (online, local, convites) é renderizado apenas pelo PHP e deve permanecer fixo.
    // Nenhuma função JS deve alterar o conteúdo de .stats-card.card-blue .breakdown.

// Se existir container de legendas customizadas acima do gráfico de check-ins por hora, removê-lo
const customLegend = document.querySelector('.custom-hourly-legend, .hourly-legend');
if (customLegend) {
    customLegend.remove();
}
