-- Lift the Suricata EVE DNS v3 queried name (dns.queries[0].rrname) into flat
-- fields so Graylog can pivot on the queried domain (grouped v3 has no flat
-- dns.rrname). Scoped to the suricata tag via the filter Match.
function extract_dns(tag, ts, record)
  local dns = record["dns"]
  if type(dns) == "table" then
    local q = dns["queries"]
    if type(q) == "table" and q[1] and q[1]["rrname"] then
      record["dns_rrname"] = q[1]["rrname"]
      record["dns_rrtype"] = q[1]["rrtype"]
      return 1, ts, record
    end
  end
  return 0, ts, record
end
