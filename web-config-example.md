/* https://docs.microsoft.com/en-us/iis/extensions/url-rewrite-module/creating-rewrite-rules-for-the-url-rewrite-module 
 https://blog.elmah.io/web-config-redirects-with-rewrite-rules-https-www-and-more/ 
 https://www.yaplex.com/blog/examples-iis-rewrite-rules/ 
 https://github.com/ScottReed/iis-redirect-generator 

Microsoft is ending its efforts to bring PHP to Windows/IIS. 
No plans to support PHP 8.0 and above. 

Self : https://www.mathias-jaekel.de/it/windows-server/php8-im-iis-unter-windows-2019-einrichten/  

https://www.iis.net/downloads/microsoft/url-rewrite URL Rewriter     */


Example of web.config :
 
 
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
  <system.webServer>
    <directoryBrowse enabled="false" />
  
 <rewrite>
 <rules>
 <rule name="HideIndex" stopProcessing="true">
 <match url="^(.*)$" />
 <conditions logicalGrouping="MatchAll">
 <add input="{HTTP_HOST}" pattern="^(.*)$" />
 <add input="{REQUEST_FILENAME}" matchType="IsFile" negate="true" />
 <add input="{REQUEST_FILENAME}" matchType="IsDirectory" negate="true" />
 </conditions>
 <action type="Rewrite" url="index.php/{R:1}" />
 <action type="Redirect" url="https://website.com/es/{R:0}" redirectType="Permanent" />
 </rule>
 </rules>
 </rewrite>

  </system.webServer>
</configuration>