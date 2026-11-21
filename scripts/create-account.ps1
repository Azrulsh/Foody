$ErrorActionPreference = 'Stop'
$accountName = Read-Host 'Name'
$accountEmail = Read-Host 'Email'
$accountPassword = Read-Host 'Password (12-72 bytes)' -AsSecureString
$accountConfirmation = Read-Host 'Confirm password' -AsSecureString
$passwordPointer = [IntPtr]::Zero
$confirmationPointer = [IntPtr]::Zero
try {
    $passwordPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($accountPassword)
    $confirmationPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($accountConfirmation)
    $accountPayload = @{
        name = $accountName
        email = $accountEmail
        password = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPointer)
        confirmation = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($confirmationPointer)
    }
    $previousEncoding = $OutputEncoding
    $OutputEncoding = [System.Text.UTF8Encoding]::new($false)
    $accountPayload | ConvertTo-Json -Compress | & php (Join-Path $PSScriptRoot 'create_account.php')
    if ($LASTEXITCODE -ne 0) { throw 'Account setup did not complete.' }
} finally {
    if ($null -ne $previousEncoding) { $OutputEncoding = $previousEncoding }
    if ($passwordPointer -ne [IntPtr]::Zero) { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPointer) }
    if ($confirmationPointer -ne [IntPtr]::Zero) { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($confirmationPointer) }
    $accountPayload = $null
    $accountPassword.Dispose()
    $accountConfirmation.Dispose()
}
