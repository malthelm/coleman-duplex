#!/usr/bin/env python3
"""Exercise the private demo over HTTP; creates two clearly labelled test enquiries."""
import datetime, html.parser, http.cookiejar, urllib.parse, urllib.request, urllib.error, sys
class Inputs(html.parser.HTMLParser):
    def __init__(self, body):
        super().__init__(); self.fields={}; self.feed(body)
    def handle_starttag(self, tag, attrs):
        a=dict(attrs)
        if tag=='input' and a.get('type')=='hidden': self.fields[a['name']]=a.get('value','')
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*args,**kwargs): return None
url=sys.argv[1].rstrip('/')
jar=http.cookiejar.CookieJar()
client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar),NoRedirect())
def request(path='/',data=None):
    req=urllib.request.Request(url+path, data=urllib.parse.urlencode(data).encode() if data is not None else None)
    try: response=client.open(req,timeout=30)
    except urllib.error.HTTPError as error: response=error
    return response.status,response.read().decode(),response.headers
status,body,_=request()
assert status==200 and 'Send demo request' in body,(status,'form unavailable')
fields=Inputs(body).fields
today=datetime.date.today()
data={**fields,'apartment':'one','guest_name':'Demo verification','email':'demo@example.invalid','arrival':str(today+datetime.timedelta(days=30)),'departure':str(today+datetime.timedelta(days=35)),'guests':'2','message':'Automated private demo verification. Not a reservation.','demo_consent':'1','website':''}
assert request(data={**data,'csrf':'invalid'})[0]==403,'CSRF must fail'
assert request(data={**data,'arrival':'2027-02-30'})[0]==422,'Invalid calendar date must fail'
assert request(data={**data,'departure':data['arrival']})[0]==422,'Same-day departure must fail'
assert request(data={**data,'apartment':'unknown'})[0]==422,'Unknown apartment must fail'
assert request(data={**data,'message':'<script>alert(1)</script>'})[0]==422,'Markup must fail'
status,_,headers=request(data=data)
assert status==303,(status,'valid enquiry failed')
assert request(data=data)[0]==303,'Transport retry should reuse receipt'
status,body,_=request('/?sent=1')
assert status==200 and 'Your request is in.' in body and 'not a booking confirmation' in body
assert request(data=data)[0]==403,'Consumed token must not create another enquiry'
status,body,_=request('/apartments/two/')
assert status==200 and 'Apartment Two' in body
fields=Inputs(body).fields
data.update(fields);data['apartment']='two';data['departure']=str(today+datetime.timedelta(days=95))
assert request(data=data)[0]==303,'Longer apartment-two enquiry failed'
for path in ['/install-cli/index_cli.php','/deployment/coleman/seed.php','/config/settings.inc.php']:
    assert request(path)[0]==403,('Private path exposed',path)
assert request('/index.php?controller=order')[0] in (301,302,303),'Checkout must redirect'
print('PASS: short/long enquiries, idempotent retry, CSRF, exact dates, apartment validation, plain-text validation, private paths, checkout redirect. Two test enquiries created.')
