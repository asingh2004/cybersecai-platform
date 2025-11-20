from datetime import datetime, timedelta
import re
import dateutil.parser


def parse_date_from_query(query: str):
    m = re.search(r'last\s+(\d+)\s+day', query, re.I)
    if m:
        n = int(m.group(1))
        end = datetime.utcnow()
        start = end - timedelta(days=n)
        return (start, end)
    if re.search(r'(today|current day)', query, re.I):
        now = datetime.utcnow()
        return (now.replace(hour=0, minute=0, second=0, microsecond=0),
                now.replace(hour=23, minute=59, second=59, microsecond=999999))
    m = re.search(r'(\d{1,2}\s+\w+\s+\d{4})', query)
    if m:
        try:
            d = dateutil.parser.parse(m.group(1), fuzzy=True).date()
            start = datetime.combine(d, datetime.min.time())
            end = datetime.combine(d, datetime.max.time())
            return (start, end)
        except Exception:
            pass
    return None
