Get-ChildItem -Path . -Recurse -Filter *.php | ForEach-Object {
    $file = $_.FullName
    Select-String -Path $file -Pattern 'require_once|require_once\(|require\(|include_once|include_once\(|include\(|require |include ' -AllMatches | ForEach-Object {
        foreach ($m in $_.Matches) {
            $text = $m.Value
            $inc = $null
            if ($text.Contains("'")) {
                $s = $text.IndexOf("'")
                $e = $text.IndexOf("'", $s + 1)
                if ($e -gt $s) { $inc = $text.Substring($s + 1, $e - $s - 1) }
            } elseif ($text.Contains('"')) {
                $s = $text.IndexOf('"')
                $e = $text.IndexOf('"', $s + 1)
                if ($e -gt $s) { $inc = $text.Substring($s + 1, $e - $s - 1) }
            }
            if ($inc) {
                $resolved = Resolve-Path -Path (Join-Path -Path (Split-Path $file) -ChildPath $inc) -ErrorAction SilentlyContinue
                $exists = $resolved -ne $null
                if ($resolved -ne $null) { $res = $resolved.Path } else { $res = (Join-Path -Path (Split-Path $file) -ChildPath $inc) }
                Write-Output "$file -> $inc -> $res -> $exists"
            }
        }
    }
}