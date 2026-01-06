param (
    [string]$SourceFile,
    [string]$DestFile,
    [string]$Format
)

$SourceFile = [System.IO.Path]::GetFullPath($SourceFile)
$DestFile = [System.IO.Path]::GetFullPath($DestFile)

Write-Output "Processing: $SourceFile -> $DestFile ($Format)"

try {
    if ($Format -eq "pdf") {
        $ext = [System.IO.Path]::GetExtension($SourceFile).ToLower()
        if ($ext -match "ppt") {
            # --- PowerPoint to PDF ---
            $ppt = New-Object -ComObject PowerPoint.Application
            # $ppt.Visible = [Microsoft.Office.Core.MsoTriState]::msoTrue # Debug
            $presentation = $ppt.Presentations.Open($SourceFile, 2, 0, 0) # ReadOnly, Untitled, WithWindow=False
            
            # ppSaveAsPDF = 32
            $presentation.SaveAs($DestFile, 32)
            $presentation.Close()
            $ppt.Quit()
            [System.Runtime.Interopservices.Marshal]::ReleaseComObject($ppt) | Out-Null
            
        } elseif ($ext -match "doc") {
            # --- Word to PDF ---
            $word = New-Object -ComObject Word.Application
            $word.Visible = $false
            $doc = $word.Documents.Open($SourceFile)
            
            # wdFormatPDF = 17
            $doc.SaveAs([ref]$DestFile, [ref]17)
            $doc.Close([ref]0) # wdDoNotSaveChanges
            $word.Quit()
            [System.Runtime.Interopservices.Marshal]::ReleaseComObject($word) | Out-Null
        }
    } elseif ($Format -eq "docx" -or $Format -eq "doc") {
            # --- PDF to Word ---
            # Conversion happens via Word Opening PDF (Reflow)
            $word = New-Object -ComObject Word.Application
            $word.Visible = $false
            
            # Open PDF (might explicitly ask for confirm conversion but usually silent in automation if configured)
            # We can try to suppress alerts
            $word.DisplayAlerts = 0 # wdAlertsNone
            
            $doc = $word.Documents.Open($SourceFile)
            
            # wdFormatXMLDocument = 12 (docx), wdFormatDocument = 0 (doc)
            $fmtCode = 12
            if ($Format -eq "doc") { $fmtCode = 0 }
            
            $doc.SaveAs([ref]$DestFile, [ref]$fmtCode)
            $doc.Close([ref]0)
            $word.Quit()
            [System.Runtime.Interopservices.Marshal]::ReleaseComObject($word) | Out-Null
    }
    
    if (Test-Path $DestFile) {
        Write-Output "SUCCESS"
    } else {
        Write-Output "FAILED: Output file not found"
        exit 1
    }

} catch {
    Write-Output "ERROR: $($_.Exception.Message)"
    exit 1
}
