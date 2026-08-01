/*
 * Baseline YARA rules for the Wazuh yara.sh Active Response. Deliberately small
 * and high-signal to keep false positives low on a homelab; extend as needed.
 */

rule eicar_test_file
{
    meta:
        description = "EICAR anti-malware test string"
        reference   = "https://www.eicar.org"
    strings:
        $eicar = "X5O!P%@AP[4\\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*"
    condition:
        $eicar
}

rule php_webshell_generic
{
    meta:
        description = "Generic PHP webshell patterns"
    strings:
        $a = "eval(base64_decode(" nocase
        $b = "eval(gzinflate(" nocase
        $c = "system($_" nocase
        $d = "shell_exec($_" nocase
        $e = "passthru($_" nocase
        $f = "assert($_" nocase
        $g = "preg_replace(\"/.*/e\"" nocase
    condition:
        2 of them
}

rule reverse_shell_generic
{
    meta:
        description = "Common reverse-shell one-liners"
    strings:
        $a = "bash -i >& /dev/tcp/"
        $b = "/bin/sh -i"
        $c = "nc -e /bin/"
        $d = "socket.socket(socket.AF_INET"
        $e = "os.dup2(s.fileno()"
        $f = "python -c 'import socket"
    condition:
        any of ($a, $c, $e) or ($d and $e) or ($f and $b)
}

rule linux_coinminer_generic
{
    meta:
        description = "Common crypto-miner config strings"
    strings:
        $a = "stratum+tcp://" nocase
        $b = "\"cryptonight\"" nocase
        $c = "xmrig" nocase
        $d = "--donate-level" nocase
    condition:
        2 of them
}
