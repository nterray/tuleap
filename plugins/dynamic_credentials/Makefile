RPM_TMP=$(HOME)/rpmbuild
PKG_NAME=tuleap-plugin-dynamic-credentials
VERSION=$(shell LANG=C cat VERSION)
# This meant to avoid having git in the docker container
# RELEASE is computed by launcher (for instance jenkins) where git is installed
# and the passed as an absolute value
ifeq ($(RELEASE),)
	RELEASE=1
endif
BASE_DIR=$(shell pwd)
RPMBUILD=rpmbuild --define "_topdir $(RPM_TMP)" --define "dist $(DIST)"

NAME_VERSION=$(PKG_NAME)-$(VERSION)

all:
	$(MAKE) DIST=.el6 rpm

rpm: $(RPM_TMP)/RPMS/noarch/$(NAME_VERSION)-$(RELEASE)$(DIST).noarch.rpm
	@echo "Results: $^"

$(RPM_TMP)/RPMS/noarch/$(NAME_VERSION)-$(RELEASE)$(DIST).noarch.rpm: $(RPM_TMP)/SOURCES/$(NAME_VERSION).tar.gz $(RPM_TMP)/SPECS/$(PKG_NAME).spec
	$(RPMBUILD) -bb $(RPM_TMP)/SPECS/$(PKG_NAME).spec

$(RPM_TMP)/SPECS/$(PKG_NAME).spec: $(BASE_DIR)/$(PKG_NAME).spec
	cat $< | \
		sed -e 's/@@TULEAP_VERSION@@/$(TULEAP_VERSION)/g' |\
		sed -e 's/@@VERSION@@/$(VERSION)/g' |\
		sed -e 's/@@RELEASE@@/$(RELEASE)/g' \
		> $@

$(RPM_TMP)/SOURCES/$(NAME_VERSION).tar.gz: $(RPM_TMP)
	[ -h $(RPM_TMP)/SOURCES/$(NAME_VERSION) ] || ln -s $(BASE_DIR) $(RPM_TMP)/SOURCES/$(NAME_VERSION)
	cd $(RPM_TMP)/SOURCES && \
		find $(NAME_VERSION)/ \(\
		-name "*~" -o\
		-path "*/.DS_Store"-o\
		\)\
		-prune -o -print |\
		cpio -o -H ustar --quiet |\
		gzip > $(RPM_TMP)/SOURCES/$(NAME_VERSION).tar.gz

$(RPM_TMP):
	@[ -d $@ ] || mkdir -p $@ $@/BUILD $@/RPMS $@/SOURCES $@/SPECS $@/SRPMS $@/TMP

docker-run:
	@[ -n "$(GID)" -a -n "$(UID)" ] || (echo "*** ERROR: UID or GID are missing" && false)
	useradd -d /build -m build
	mkdir -p /build/src/plugins/
	cp -Rf /tuleap/plugins/dynamic_credentials/ /build/src/plugins/ && chown -R build /build/src
	su --login --command "make -C /build/src/plugins/dynamic_credentials all TULEAP_VERSION=$(shell LANG=C cat /tuleap/VERSION) RELEASE=$(RELEASE)" build
	install -o $(UID) -g $(GID) -m 0644 /build/rpmbuild/RPMS/noarch/*.rpm /output
