Welcome to William Development Tools

This is simple PHP Library Class that works on Machine Learning Word Analysis
the library currently works for language of Indonesia and the word stemmer for analysis
using naive-bayes technology credits to :

https://github.com/biobii/naive-bayes-text-classifier

if you wish to use another language please use different stemmer class for text classifications
so that word analysis and training works properly.

Notes :
- the keyword folder contains JSON category product for machineword to easily identify words, you may change this content
  also the $this->registered inside construct should be change.
- keyword/supportive are word list for adjective and supportive word to exclude them in word analysis
- function searchword in machineword.php should be define to find unknown word and help word analysis
- keyword/training.json contains word from training process that help identify the word that not included inside keyword category

How to use :
you may include this machineword.php file in your applications
and call this method :
analyze(STRING, 1/0 = for switching training process)

please contact me for further information : william.akihisa@gmail.com