# Web Services module

A module for exposing defined business logic via URLs, in web consumable
formats, and for providing access to consumers of these webservices via 
token access. 

These are NOT strictly speaking REST services, in that a single URL isn't
meant to be accessible via GET and POST; it is RPC over HTTP.

## Maintainer Contacts

*  Marcus Nyeholt <marcus@silverstripe.com.au>

## Versions

The master branch of this module is currently aiming for SilverStripe 3.1 compatibility

* [SilverStripe 3.0 compatible version](https://github.com/nyeholt/silverstripe-webservices/tree/2.0)
* [SilverStripe 2.4 compatible version](https://github.com/nyeholt/silverstripe-webservices/tree/ss24)

## Requirements

* SilverStripe 2.4+

## Installation

* Place this directory in the root of your SilverStripe installation. 
* Run dev/build 
* Creates your service class, which will be accessible by both controller 
  code and directly via the web
* Access your service via the appropriate URL, eg

	http://yourUrl/jsonservice/DummyWeb/myMethod?param=stuff&SecurityID=security_id_here


* Use the response

## Documentation

The webservices module will automatically translate a url based request to a
system service from URL + parameters to a method and variables, then 
automatically convert the response into a web accessible format (JSON and XML
supported, though XML is not yet complete). Part of its goal is to encourage
service oriented architectures in SilverStripe projects so that business logic
is encapsulated in a layer away from controllers (an all too common problem). 

URLs are decomposed as follows

    (type)/(service)/(method)?(paramName)=(value)&token=(token)[&SecurityID=(securityID)]

* type: either jsonservice or webservice, indicates the desired output format
* service: the name of a service class (see below). It expects the service to
  end in the string 'Service' - in fact, that will be automatically appended to
  the name passed through here. 
* method: the name of the method to call on the service
* paramName/value: key/value pairs representing parameters. The paramName MUST
  match the name of the parameter in the method declaration. 
  * See the note below for information on how to pass DataObjects
* token | SecurityID: Each request must be verified; this is either through
  the use of a token parameter (which matches up with a user's API token) OR
  by passing the security ID for a user who has already logged in. The token 
  can be found by looking on the user's details tab in the CMS

Included is an example service for creating events in a calendar using the
event_calendar module. You can create events for this using the following
curl statement

    curl -X POST -d "token={your token here}&parentCalendar={ID of calendar}&title=My new event&content=This is an event on a day here and now&startDate=2012-04-21" http://{your site url}/jsonservice/calendarweb/createEvent
    

### Passing DataObjects

In some cases you'll be wanting to pass data objects to your service method. 
To allow that from URL parameters, you can instead pass an ID and Type parameter
which will be converted for use appropriately. For example, this method signature

	public function myMethod(DataObject $page) {

	}

can be accessed via

	jsonservice/Service/myMethod?pageID=1&pageType=Page

The module will take care of converting that to the relevant object type

### Allowing access to services and their methods

To be able to call methods on a service, the service has to be marked as being
accessible - this is done in one of two ways

* Implement the `WebServiceable` interface
* Implement the `webEnabledMethods` method, which returns an map of method names
  and the request type that they support (GET or POST only supported currently).
  Note that to perform user level access control, you should perform the checks
  in the method directly, OR wrap user checking logic around the array this
  method generates. 

So, by default, it's not possible to arbitrarily call any service. Also, the
`webEnabledMethods` call does NOT use SilverStripe's hasMethod construct (as
it is not expected that a service carry the overhead of inheriting from 
'Object') so at the moment this is not overrideable using extensions. There will
be ways to update this via extensions at a later date. 

### Passing parameters via URL

The webservice controller supports passing parameter/value pairs via the URL. 
For example, the following request will have the parameters extracted

/jsonservice/my/method/name/something/id/2

Will call My::method(), passing parameters of 

* name = something
* id = 2

### File uploads

Files can be handled as either multipart uploads or by posting the raw file to 
the webservice, typically in conjuction with the URL parameter referencing above. 

To have the file accessible to your service method, you MUST have a method 
parameter named "file" and a request type of POST; the raw post body of the 
request will be placed in the "file" variable, and any parameters found on the
URL also passed through. 

eg in your service 

    public function method($file, $name) {
    
    }
    
sending a post such as

    curl -X POST -d @somefile.jpg http://site/jsonservice/my/method/name/myfile.jpg

will send the body of somefile.jpg in the $file variable, and $name == 'myfile.jpg'

### Sending POST body

Instead of passing parameters via the URL, you can send all method parameters
in a JSON object that is the body of a POST request. For example, the JSON data

```JSON
{
    "parentCalendar": "2",
    "title": "This is a calendar event" 
}
```

will pass the two fields as the parameters to whichever method is named by the
URL. The important thing is that you MUST send the `Content-Type: application/json`
header. 


### Tokens

Authentication tokens can be passed either in the URL as the _token_ GET 
parameter, or by including the `X-Auth-Token` header in the request. 


The module will automatically add a 'Token' field to user objects, and
generate a random token when a user account is created. This can be used
to access the API without being logged in first. 

Otherwise, if the user is logged in, they can pass through the security token
as a parameter (appropriately named, by default SecurityID) with the request
which will grant the user access. This can be disabled by setting the 
`allowPublicAccess` parameter for the WebserviceAuthenticator

    Injector:
      WebserviceAuthenticator:
        properties:
          allowPublicAccess: 0

	










