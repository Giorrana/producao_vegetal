// export.js - Excel and PDF Export Utility

// Inject Print CSS dynamically to avoid messing up screen styles
(function() {
    const style = document.createElement('style');
    style.innerHTML = `
        @media print {
            html, body, .app-layout, .main-wrapper, .main-content, .content-wrapper {
                height: auto !important;
                min-height: 0 !important;
                max-height: none !important;
                overflow: visible !important;
                overflow-x: visible !important;
                overflow-y: visible !important;
            }
            div[style*="overflow"], div[style*="overflow-x"], div[style*="overflow-y"], .svg-container, .table-responsive, table {
                overflow: visible !important;
                overflow-x: visible !important;
                overflow-y: visible !important;
            }
            .sidebar, .topbar, .overlay, .menu-btn, .btn-logout, .add-btn, .add-btn-filter, .filters-container, .plantio-actions, .crop-actions, .btn-export, .page-header button {
                display: none !important;
            }
            .pillar-panel:not(.active) {
                display: none !important;
            }
            .pillar-panel.active {
                display: block !important;
                height: auto !important;
                overflow: visible !important;
            }
            .main-wrapper {
                margin-left: 0 !important;
                padding: 0 !important;
                width: 100% !important;
            }
            body {
                background: white !important;
                color: black !important;
                font-size: 12pt !important;
            }
            .content-wrapper {
                padding: 0 !important;
            }
            .report-card, .plantio-card, .crop-card, .settings-card, .table-container {
                border: 1px solid #ccc !important;
                box-shadow: none !important;
                page-break-inside: avoid !important;
                margin-bottom: 20px !important;
                background: white !important;
            }
        }
    `;
    document.head.appendChild(style);
})();

// Export HTML Table to Excel (CSV format with BOM)
function exportTableToExcel(tableId, filename = 'relatorio') {
    const table = document.getElementById(tableId);
    if (!table) {
        console.error("Table not found:", tableId);
        return;
    }
    
    let csv = [];
    const rows = table.querySelectorAll("tr");
    
    for (let i = 0; i < rows.length; i++) {
        const row = [], cols = rows[i].querySelectorAll("td, th");
        
        for (let j = 0; j < cols.length; j++) {
            // Clean up cell text
            let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, "").replace(/(\s\s+)/gm, " ");
            data = data.replace(/"/g, '""'); // Escape double quotes
            row.push('"' + data + '"');
        }
        
        csv.push(row.join(";"));
    }
    
    // Create CSV string with UTF-8 BOM (Byte Order Mark) so Excel reads accents correctly
    const csvContent = "\uFEFF" + csv.join("\n");
    const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
    
    const link = document.createElement("a");
    if (link.download !== undefined) {
        const url = URL.createObjectURL(blob);
        link.setAttribute("href", url);
        link.setAttribute("download", filename + ".csv");
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}

// Print Current Page / Specific Section to PDF
function exportToPDF() {
    window.print();
}
