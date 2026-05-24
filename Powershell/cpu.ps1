param(
    [parameter(Mandatory, ValueFromPipeline)]
    [string]$hostname
)

Get-Process -ComputerName "$hostname" | where-object cpu -gt 50