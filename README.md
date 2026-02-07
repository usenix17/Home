# Infrastructure or Organization as Code (IaC or OaC)

## Organization as Code

As any organization depends more and more on technology I am finding that the
whole of the organization can be captured as code.

## Documentation is Text

Documentation systems have grown to become isolated silos that can at times
become useless in an outage situation. Using simple txt files to capture
mission critical information can become useful. Markdown or markup languages
can be used if desired. A Git repo full of text files can survive an outage
better than a vendor specific address book. Markdown or .md files are text
files that can be rendered to look pretty but readable as they are.

## Infrastructure as Code

Goals are:

1. Distributed survival of Infrastructure
1. Versioned Infrastructure
1. Boot-strap-able

In this example I use Ansible in a less-than-standard way. The idea is that
this code can build the infrastructure as a primary deployment or regardless
of any disaster.

## Resources

* https://en.wikipedia.org/wiki/Out-of-band_management
